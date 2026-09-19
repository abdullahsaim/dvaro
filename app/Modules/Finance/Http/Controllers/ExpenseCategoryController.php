<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\ExpenseCategoryRequest;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Expense categories — add / rename / hide (never delete). Admin + Accounts
 * only (ExpensePolicy::manageCategories). {category} binds via TenantScope.
 */
class ExpenseCategoryController extends Controller
{
    public function store(ExpenseCategoryRequest $request, ExpenseCategoryService $categories): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('manageCategories', Expense::class);

        $categories->create($request->string('name')->toString());

        return back()->with('success', __('common.expenses.category_saved'));
    }

    public function update(
        ExpenseCategoryRequest $request,
        ExpenseCategory $category,
        ExpenseCategoryService $categories,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('manageCategories', Expense::class);

        $categories->update($category, $request->string('name')->toString(), $request->boolean('is_hidden'));

        return back()->with('success', __('common.expenses.category_saved'));
    }
}
