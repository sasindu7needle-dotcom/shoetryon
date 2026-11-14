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
            'fit.scale' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'fit.rotation' => ['required', 'array:0,1,2', 'size:3'],
            'fit.rotation.*' => ['required', 'numeric', 'between:-360,360'],
            'fit.position' => ['required', 'array:0,1,2', 'size:3'],
            'fit.position.*' => ['required', 'numeric', 'between:-100,100'],
            'fit.stretch' => ['nullable', 'array:0,1,2', 'size:3'],
            'fit.stretch.*' => ['required', 'numeric', 'gt:0', 'max:10'],
            'leg_mask' => ['required', 'boolean'],
        ];
    }

    /**
     * The fit and leg mask as the submission stores them. A stretch (only
     * Claude sets one) is kept unless it is 1 on every axis.
     *
     * @return array{fit: array{scale: float, rotation: list<float>, position: list<float>, stretch?: list<float>}, leg_mask: bool}
     */
    public function previewFit(): array
    {
        $toFloats = fn (array $values): array => array_map('floatval', array_values($values));
        $fit = $this->validated('fit');

        $previewFit = [
            'scale' => (float) $fit['scale'],
            'rotation' => $toFloats($fit['rotation']),
            'position' => $toFloats($fit['position']),
        ];

        $stretch = $toFloats($fit['stretch'] ?? [1, 1, 1]);

        if ($stretch !== [1.0, 1.0, 1.0]) {
            $previewFit['stretch'] = $stretch;
        }

        return [
            'fit' => $previewFit,
            'leg_mask' => $this->boolean('leg_mask'),
        ];
    }
}
