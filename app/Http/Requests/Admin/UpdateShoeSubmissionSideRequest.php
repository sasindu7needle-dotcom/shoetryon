<?php

namespace App\Http\Requests\Admin;

use App\ShoeSide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShoeSubmissionSideRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shoe_side' => ['required', Rule::enum(ShoeSide::class)],
        ];
    }

    /**
     * The foot the admin now says the shoe is for.
     */
    public function side(): ShoeSide
    {
        return $this->enum('shoe_side', ShoeSide::class);
    }
}
