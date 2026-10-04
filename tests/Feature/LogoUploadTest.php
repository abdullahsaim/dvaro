<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Rules\ValidLogoFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * Client feedback: "when we insert a new logo then it gives an error". Two
 * real issues were found and fixed:
 *
 *  1. The validation rule (`mimes:png,jpg,jpeg,svg,webp`) trusts PHP's
 *     fileinfo MIME guess, which is well known to misdetect SVG — a genuine
 *     SVG export commonly fails validation with no useful explanation. Fixed
 *     with ValidLogoFile: raster formats still get a real content check, SVG
 *     gets an extension + lightweight XML sniff instead of an unreliable
 *     single MIME guess.
 *  2. A storage write failure (permissions, a missing directory on a fresh
 *     deploy) was UNCAUGHT — a raw 500 reaching the browser with no
 *     explanation. Both upload endpoints now catch it and log the real cause
 *     while returning a normal, actionable form error.
 */
class LogoUploadTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeAdmin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    // ── ValidLogoFile rule ───────────────────────────────────────────────────

    private function validate(UploadedFile $file): Validator
    {
        return validator(['logo' => $file], ['logo' => [new ValidLogoFile]]);
    }

    public function test_a_genuine_png_passes(): void
    {
        $this->assertTrue($this->validate(UploadedFile::fake()->image('logo.png'))->passes());
    }

    public function test_a_genuine_svg_passes_even_though_finfo_is_unreliable_for_it(): void
    {
        // A real SVG, uploaded with whatever content-type the browser happened
        // to send (often NOT image/svg+xml — the whole reason the old
        // mimes: rule failed on real SVGs). The rule must not depend on it.
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>',
        );

        $this->assertTrue($this->validate($svg)->passes());
    }

    public function test_an_arbitrary_file_renamed_to_svg_is_still_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('logo.svg', 'not actually markup at all');

        $this->assertTrue($this->validate($fake)->fails());
    }

    public function test_a_disguised_non_image_file_is_rejected(): void
    {
        // UploadedFile::fake()->createWithContent() hardcodes the MIME type
        // from the extension for test convenience, so it can't simulate a real
        // mismatch — a genuine UploadedFile around real file content is needed
        // to exercise the actual finfo-based raster check.
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($path, 'this is plain text, not an image');
        $disguised = new UploadedFile($path, 'logo.png', null, null, true);

        try {
            $this->assertTrue($this->validate($disguised)->fails());
        } finally {
            @unlink($path);
        }
    }

    public function test_an_unsupported_extension_is_rejected(): void
    {
        $this->assertTrue($this->validate(UploadedFile::fake()->create('logo.gif', 10))->fails());
    }

    // ── The upload endpoints never surface a raw 500 ────────────────────────

    public function test_company_logo_upload_works_end_to_end(): void
    {
        Storage::fake('public');
        $t = $this->makeTenant('logo-a');
        $admin = $this->makeAdmin($t);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/settings/company/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertSessionHasNoErrors();
    }

    public function test_company_logo_upload_rejects_a_bad_svg_with_a_clean_validation_error(): void
    {
        Storage::fake('public');
        $t = $this->makeTenant('logo-b');
        $admin = $this->makeAdmin($t);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/settings/company/logo", [
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', 'garbage'),
            ])
            ->assertSessionHasErrors('logo'); // a clean 422/validation error, never a 500
    }

    public function test_invoice_logo_upload_also_accepts_a_genuine_svg(): void
    {
        Storage::fake('public');
        $t = $this->makeTenant('logo-c');
        $admin = $this->makeAdmin($t);

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        );

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/settings/invoice-template/logo", ['logo' => $svg])
            ->assertSessionHasNoErrors();
    }

    public function test_staff_cannot_upload_a_company_logo(): void
    {
        Storage::fake('public');
        $t = $this->makeTenant('logo-d');
        app()->instance('current_tenant', $t);
        $staff = TenantUser::create([
            'name' => 'Staffer', 'email' => 'staff@logo-d.test', 'password' => 'secret123',
            'role' => TenantUser::ROLE_STAFF, 'is_active' => true,
        ]);

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$t->slug}/settings/company/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
    }
}
