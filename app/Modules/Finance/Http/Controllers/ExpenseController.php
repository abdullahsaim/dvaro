<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesForUser;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RecordExpenseAction;
use App\Modules\Finance\Actions\UpdateExpenseAction;
use App\Modules\Finance\Actions\VoidExpenseAction;
use App\Modules\Finance\DTOs\ExpenseDTO;
use App\Modules\Finance\Http\Requests\ExpenseRequest;
use App\Modules\Finance\Http\Requests\VoidExpenseRequest;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Policies\ExpensePolicy;
use App\Modules\Finance\Services\ExpenseCategoryService;
use App\Modules\Finance\Services\ExpenseReportService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Services\ReportingService;
use App\Services\FileUrlService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance → Expenses (tenant app). Thin: authorize → Action/Service → respond.
 *
 * AUTHORIZATION: every action goes through ExpensePolicy via
 * Gate::forUser(auth('tenant')->user()) — never $this->authorize() (wrong
 * guard). {expense} binds through TenantScope (cross-tenant id → 404).
 */
class ExpenseController extends Controller
{
    use PaginatesForUser;

    public function index(
        Request $request,
        ExpenseReportService $reports,
        ReportingService $reporting,
        ExpenseCategoryService $categories,
    ): Response {
        $user = auth('tenant')->user();
        Gate::forUser($user)->authorize('viewAny', Expense::class);

        $categories->ensureDefaults(app('current_tenant'));

        // Filters — default window is the current month.
        $from = $this->date($request->query('from')) ?? today()->startOfMonth();
        $to = $this->date($request->query('to')) ?? today()->endOfMonth();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $categoryId = $request->integer('category') ?: null;
        $vehicleId = $request->integer('vehicle') ?: null;
        $showVoided = $request->boolean('voided');

        $expenses = Expense::query()
            ->with(['category:id,name', 'vehicle:id,registration_number,make,model', 'creator:id,name'])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when(! $showVoided, fn ($q) => $q->active())
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $gate = Gate::forUser($user);
        $expenses->through(fn (Expense $e) => tap($e, function (Expense $x) use ($gate) {
            $x->setAttribute('can_edit', $gate->allows('update', $x));
            $x->setAttribute('can_void', $gate->allows('void', $x));
        }));

        $fy = $reporting->australianFY();

        return Inertia::render('Finance/Expenses/Index', [
            'expenses' => $expenses,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'category' => $categoryId,
                'vehicle' => $vehicleId,
                'voided' => $showVoided,
            ],
            'summary' => [
                'filtered' => $reports->summary($from, $to, $categoryId, $vehicleId),
                'month' => $reports->summary(today()->startOfMonth(), today()->endOfMonth())['totals'],
                'fy' => $reports->summary($fy['from'], $fy['to'])['totals'],
                'fy_label' => 'FY'.$fy['from']->format('y').'/'.$fy['to']->format('y'),
                'fy_from' => $fy['from']->toDateString(),
                'fy_to' => $fy['to']->toDateString(),
            ],
            'categories' => ExpenseCategory::query()->ordered()->get(['id', 'name', 'system_key', 'is_hidden']),
            'vehicles' => Vehicle::query()->orderBy('registration_number')->get(['id', 'registration_number', 'make', 'model']),
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'canManage' => ExpensePolicy::isManager($user),
        ]);
    }

    public function store(ExpenseRequest $request, RecordExpenseAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Expense::class);

        $action->execute(ExpenseDTO::fromRequest($request), $request->file('receipt'), auth('tenant')->id());

        return back()->with('success', __('common.expenses.recorded'));
    }

    public function update(ExpenseRequest $request, Expense $expense, UpdateExpenseAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('update', $expense);

        $action->execute(
            $expense,
            ExpenseDTO::fromRequest($request),
            $request->file('receipt'),
            $request->boolean('remove_receipt'),
        );

        return back()->with('success', __('common.expenses.updated'));
    }

    public function void(VoidExpenseRequest $request, Expense $expense, VoidExpenseAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('void', $expense);

        $action->execute($expense, $request->string('reason')->toString(), auth('tenant')->id());

        return back()->with('success', __('common.expenses.voided'));
    }

    /**
     * SENSITIVE receipt: auth-checked here, then a 15-minute signed URL on the
     * default disk (FileUrlService) — never a direct or permanent path.
     */
    public function receipt(Expense $expense, FileUrlService $fileUrls): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $expense);

        abort_if($expense->receipt_path === null, 404);

        return redirect()->away($fileUrls->temporaryUrl($expense->receipt_path));
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
