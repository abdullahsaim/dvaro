<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\CmsContentBlock;
use App\Modules\CMS\Services\CmsContentService;
use App\Modules\SuperAdmin\Http\Requests\UpdateCmsContentRequest;
use App\Modules\SuperAdmin\Http\Requests\UpdateCmsImageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing-page CMS editor (super admin panel).
 *
 * Content-level access only (contentAccess gate: platform_owner or
 * content_manager). Authorization uses the load-bearing forUser pattern —
 * Gate::forUser(auth('superadmin')->user()) — NEVER $this->authorize(), which
 * would resolve the empty default (web) guard. Reads/writes go through
 * CmsContentService so every edit busts the relevant cache immediately.
 */
class CmsContentController extends Controller
{
    public function __construct(
        private readonly CmsContentService $cms,
    ) {}

    /**
     * Editor tab order — mirrors the order the sections appear on the public
     * pages (unknown/new sections fall to the end alphabetically).
     */
    private const SECTION_ORDER = [
        'branding', 'hero', 'stats', 'features', 'how_it_works', 'about',
        'values', 'testimonials', 'faq', 'cta', 'contact',
    ];

    /**
     * All content blocks grouped by section for the editor.
     */
    public function index(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $order = array_flip(self::SECTION_ORDER);

        $sections = CmsContentBlock::query()
            ->orderBy('section')
            ->orderBy('sort_order')
            ->get()
            ->sortBy(fn (CmsContentBlock $b) => [
                $order[$b->section] ?? PHP_INT_MAX,
                $b->section,
                $b->sort_order,
            ])
            ->values()
            ->map(fn (CmsContentBlock $b) => [
                'key' => $b->key,
                'type' => $b->type,
                'section' => $b->section,
                'sort_order' => $b->sort_order,
                'content' => $b->content,
                'image_url' => $b->imageUrl(),
            ])
            ->groupBy('section');

        return Inertia::render('SuperAdmin/Cms/Index', [
            'sections' => $sections,
        ]);
    }

    /**
     * Update a single text/richtext block.
     */
    public function update(UpdateCmsContentRequest $request, string $key): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $this->cms->update($key, $request->validated('content'));

        return back()->with('success', __('common.cms.content_updated'));
    }

    /**
     * Upload/replace an image block's image.
     */
    public function updateImage(UpdateCmsImageRequest $request, string $key): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $this->cms->updateImage($key, $request->file('image'));

        return back()->with('success', __('common.cms.image_updated'));
    }
}
