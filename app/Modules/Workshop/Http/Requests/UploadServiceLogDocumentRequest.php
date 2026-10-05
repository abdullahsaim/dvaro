<?php

namespace App\Modules\Workshop\Http\Requests;

use App\Modules\Workshop\Actions\UploadServiceLogDocumentAction;
use Illuminate\Foundation\Http\FormRequest;

class UploadServiceLogDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', UploadServiceLogDocumentAction::EXTENSIONS),
                'max:'.UploadServiceLogDocumentAction::MAX_KB,
            ],
        ];
    }
}
