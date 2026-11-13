<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFeetFitRequest extends FormRequest
{
    /**
     * How the admin lined the GLB up on their feet in the preview, in the
     * same shape as a shoe's AR fit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fit' => ['required', 'array'],
            'fit.scale' => ['required', 'numeric', 'gt:0', 'max:10'],
            'fit.rotation' => ['required', 'array:0,1,2', 'size:3'],
            'fit.rotation.*' => ['required', 'numeric', 'between:-360,360'],
            'fit.position' => ['required', 'array:0,1,2', 'size:3'],
            'fit.position.*' => ['required', 'numeric', 'between:-100,100'],
            'leg_mask' => ['required', 'boolean'],
        ];
    }

    /**
     * The fit and leg mask as the submission stores them.
     *
     * @return array{fit: array{scale: float, rotation: list<float>, position: list<float>}, leg_mask: bool}
     */
    public function previewFit(): array
    {
        $toFloats = fn (array $values): array => array_map('floatval', array_values($values));
        $fit = $this->validated('fit');

        return [
            'fit' => [
                'scale' => (float) $fit['scale'],
                'rotation' => $toFloats($fit['rotation']),
                'position' => $toFloats($fit['position']),
            ],
            'leg_mask' => $this->boolean('leg_mask'),
        ];
    }
}
