<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShoeFitRoundRequest extends FormRequest
{
    /**
     * Largest phone screenshot sent to Claude, in kilobytes (5 MB, under the
     * Claude API's image limit).
     */
    public const MAX_SCREENSHOT_KILOBYTES = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
            'screenshot' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_SCREENSHOT_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'screenshot.max' => 'The screenshot must be 5 MB or smaller.',
        ];
    }
}
