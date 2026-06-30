<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to a single text/richtext CMS block (super admin).
 *
 * The block `key` arrives as a route segment; it is merged into the validated
 * data and checked against cms_content_blocks so an unknown key is rejected.
 * Authorization is enforced in the controller via the contentAccess gate.
 */
class UpdateCmsContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['key' => $this->route('key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', Rule::exists('cms_content_blocks', 'key')],
            // Covers text and richtext blocks. 5000 chars is ample for a hero
            // line or an about-us body; image blocks use updateImage() instead.
            'content' => ['required', 'string', 'max:5000'],
        ];
    }
}
