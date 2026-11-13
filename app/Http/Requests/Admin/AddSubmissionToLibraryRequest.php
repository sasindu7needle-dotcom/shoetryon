<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddSubmissionToLibraryRequest extends FormRequest
{
    /**
     * Largest DeepAR effect accepted, in kilobytes (50 MB).
     */
    public const MAX_EFFECT_KILOBYTES = 51200;

    /**
     * Get the validation rules that apply to the request. A .deepar file has
     * no registered MIME type, so its extension is what identifies it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'effect' => ['required', 'file', 'extensions:deepar', 'max:'.self::MAX_EFFECT_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['effect' => 'DeepAR effect'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effect.extensions' => 'The DeepAR effect must be a .deepar file exported from DeepAR Studio.',
            'effect.max' => 'The DeepAR effect must be 50 MB or smaller.',
        ];
    }
}
