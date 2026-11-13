<?php

namespace App\Http\Requests\N8n;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFitRoundResultRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The fit has the
     * same shape and limits as the admin's AR fit form; it is only needed
     * when Claude changed something.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'changed' => ['required', 'boolean'],
            'note' => ['required', 'string', 'max:2000'],
            'fit' => ['exclude_unless:changed,true', 'required', 'array'],
            'fit.scale' => ['exclude_unless:changed,true', 'required', 'numeric', 'gt:0', 'max:1000'],
            'fit.rotation' => ['exclude_unless:changed,true', 'required', 'array:0,1,2', 'size:3'],
            'fit.rotation.*' => ['required', 'numeric', 'between:-360,360'],
            'fit.position' => ['exclude_unless:changed,true', 'required', 'array:0,1,2', 'size:3'],
            'fit.position.*' => ['required', 'numeric', 'between:-1000,1000'],
            'fit.stretch' => ['exclude_unless:changed,true', 'nullable', 'array:0,1,2', 'size:3'],
            'fit.stretch.*' => ['required', 'numeric', 'gt:0', 'max:10'],
        ];
    }

    /**
     * Claude's fit in the shape the shoes table stores. A stretch of 1 on
     * every axis is left out, as the admin's fit form does.
     *
     * @return array{scale: float, rotation: list<float>, position: list<float>, stretch?: list<float>}
     */
    public function fit(): array
    {
        $toFloats = fn (array $values): array => array_map('floatval', array_values($values));
        $fit = $this->validated('fit');

        $attributes = [
            'scale' => (float) $fit['scale'],
            'rotation' => $toFloats($fit['rotation']),
            'position' => $toFloats($fit['position']),
        ];

        $stretch = $toFloats($fit['stretch'] ?? [1, 1, 1]);

        if ($stretch !== [1.0, 1.0, 1.0]) {
            $attributes['stretch'] = $stretch;
        }

        return $attributes;
    }
}
