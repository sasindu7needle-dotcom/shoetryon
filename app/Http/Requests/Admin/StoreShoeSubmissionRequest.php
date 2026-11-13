<?php

namespace App\Http\Requests\Admin;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShoeSubmissionRequest extends FormRequest
{
    /**
     * The photos the n8n workflow's form takes, in the order it lists them.
     *
     * @var array<string, string>
     */
    public const PHOTOS = [
        'front_photo' => 'Front (toe towards the camera)',
        'right_photo' => 'Right side',
        'back_photo' => 'Back (heel towards the camera)',
        'left_photo' => 'Left side',
    ];

    /**
     * The one photo every shoe needs. The others are optional: more photos
     * give Tripo more of the shoe to work from, so the 3D model is more accurate.
     */
    public const REQUIRED_PHOTO = 'front_photo';

    /**
     * Largest photo n8n accepts, in kilobytes (20 MB).
     */
    public const MAX_PHOTO_KILOBYTES = 20480;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $photo = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PHOTO_KILOBYTES];
        $photoRules = [];

        foreach (array_keys(self::PHOTOS) as $photoField) {
            $photoRules[$photoField] = [$photoField === self::REQUIRED_PHOTO ? 'required' : 'nullable', ...$photo];
        }

        return [
            'name' => ['required', 'string', 'max:80', 'regex:/[\pL\pN]/u'],
            'shoe_type' => ['required', Rule::in(['low', 'boot'])],
            'length_cm' => ['required', 'numeric', 'between:15,40'],
            ...$photoRules,
        ];
    }

    /**
     * The workflow names the shoe's files after its id, so a name whose id is
     * already in the library or on its way from n8n is refused.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $slug = $this->slug();

                if ($slug === '') {
                    $validator->errors()->add('name', 'The name needs at least one letter (a–z) or number.');
                } elseif (Shoe::query()->where('slug', $slug)->exists()) {
                    $validator->errors()->add('name', "The library already has a shoe with the id “{$slug}”. Choose another name.");
                } elseif (ShoeSubmission::query()->where('slug', $slug)->whereIn('status', ShoeSubmissionStatus::claimingId())->exists()) {
                    $validator->errors()->add('name', "A shoe with the id “{$slug}” is already being made.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shoe_type' => 'type',
            'length_cm' => 'shoe length',
            'front_photo' => 'front photo',
            'right_photo' => 'right side photo',
            'back_photo' => 'back photo',
            'left_photo' => 'left side photo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['name.regex' => 'The name needs at least one letter or number.'];

        foreach (array_keys(self::PHOTOS) as $photoField) {
            $messages["{$photoField}.max"] = 'The :attribute must be 20 MB or smaller.';
            $messages["{$photoField}.mimes"] = 'The :attribute must be a JPEG or PNG.';
            $messages["{$photoField}.uploaded"] = 'The :attribute did not upload. It may be bigger than the server allows.';
        }

        return $messages;
    }

    /**
     * The shoe id the n8n workflow will give this name.
     */
    public function slug(): string
    {
        return ShoeSubmission::slugFor((string) $this->input('name'));
    }
}
