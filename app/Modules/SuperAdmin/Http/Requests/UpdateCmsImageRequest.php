<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an image upload for an image CMS block (super admin).
 *
 * The block `key` arrives as a route segment and must exist + be an image-type
 * block. The file is type- and size-validated (≤5MB). Authorization is enforced
 * in the controller via the contentAccess gate.
 */
class UpdateCmsImageRequest extends FormRequest
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
            'key' => [
                'required',
                'string',
                // Must be an existing block AND declared as an image block —
                // text/richtext blocks are edited via the content endpoint.
                Rule::exists('cms_content_blocks', 'key')->where('type', 'image'),
            ],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
