<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionFitRoundRequest extends FormRequest
{
    /**
     * What Claude is asked when the admin sends only the photo.
     */
    public const DEFAULT_COMMENT = 'Look at how the shoe sits on my feet in the photo and fix the fit so my feet are inside the shoes.';

    /**
     * A photo taken in the feet preview, and optionally what looks wrong.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'min:5', 'max:1000'],
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.StoreShoeFitRoundRequest::MAX_SCREENSHOT_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.max' => 'The photo must be 5 MB or smaller.',
        ];
    }

    /**
     * The admin's comment, or the default question when they left it empty.
     */
    public function comment(): string
    {
        return trim((string) $this->validated('comment')) ?: self::DEFAULT_COMMENT;
    }
}
