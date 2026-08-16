<?php

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Events\DemoRequestSubmitted;
use App\Modules\CMS\Http\Requests\ContactFormRequest;
use App\Modules\CMS\Models\DemoRequest;
use App\Modules\CMS\Services\CmsContentService;
use App\Modules\SaasCore\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public landing website — homepage, pricing, about and contact.
 *
 * PUBLIC: no auth, no tenant middleware (these routes live in the bare 'web'
 * group). All editable copy comes from CmsContentService (cache-first); the
 * pricing section ALWAYS reads live Plan data — pricing is never hardcoded and
 * inactive plans are never shown.
 */
class PublicLandingController extends Controller
{
    public function __construct(
        private readonly CmsContentService $cms,
    ) {}

    /**
     * Homepage: hero, stats, features, how-it-works, a pricing preview,
     * testimonials, FAQ and the closing CTA band.
     */
    public function index(): Response
    {
        return Inertia::render('Public/Landing', [
            'hero' => $this->section('hero'),
            'stats' => $this->section('stats'),
            'features' => $this->section('features'),
            'howItWorks' => $this->section('how_it_works'),
            'about' => $this->section('about'),
            'testimonials' => $this->section('testimonials'),
            'faq' => $this->section('faq'),
            'cta' => $this->section('cta'),
            'contact' => $this->section('contact'),
            'plans' => $this->activePlans(),
        ]);
    }

    /**
     * Dedicated pricing page — every active plan with pricing, modules, limits,
     * plus the shared FAQ and CTA content.
     */
    public function pricing(): Response
    {
        return Inertia::render('Public/Pricing', [
            'plans' => $this->activePlans(),
            'faq' => $this->section('faq'),
            'cta' => $this->section('cta'),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Public/About', [
            'about' => $this->section('about'),
            'values' => $this->section('values'),
            'stats' => $this->section('stats'),
            'cta' => $this->section('cta'),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact', [
            'contact' => $this->section('contact'),
        ]);
    }

    /**
     * Handle a contact-form submission. Persisted as a DemoRequest so it lands
     * in the same super admin queue; name maps to contact_name and the enquiry
     * is stored in `message`. Company name is unknown on a contact enquiry, so
     * it is recorded as the contact's name.
     */
    public function submitContact(ContactFormRequest $request): RedirectResponse
    {
        $demoRequest = DemoRequest::query()->create([
            'company_name' => $request->validated('name'),
            'contact_name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'message' => $request->validated('message'),
            'status' => DemoRequest::STATUS_NEW,
        ]);

        DemoRequestSubmitted::dispatch($demoRequest);

        return back()->with('success', __('common.cms.contact_sent'));
    }

    /**
     * A section's blocks as a key => display-value map for the frontend.
     *
     * @return array<string, mixed>
     */
    private function section(string $section): array
    {
        return $this->cms->getSection($section)
            ->mapWithKeys(fn (array $b) => [$b['key'] => $b['value']])
            ->all();
    }

    /**
     * Live, public-safe view of the active plans, ordered for display. Prices
     * stay in integer cents (the frontend formats via useCurrency).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function activePlans(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_annual' => $plan->price_annual,
                'is_free' => $plan->is_free,
                'trial_days' => $plan->trial_days,
                'modules' => $plan->modules ?? [],
                'limits' => $plan->limits ?? [],
            ]);
    }
}
