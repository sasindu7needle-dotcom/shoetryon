<?php

namespace App\Http\Requests\Admin;

use App\Models\Shoe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateShoeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $axis = ['required', 'numeric', 'between:-1000,1000'];

        return [
            'name' => ['required', 'string', 'max:80'],
            'is_visible' => ['boolean'],
            'position' => ['required', 'integer', 'min:1', 'max:'.max(1, Shoe::query()->count())],
            'return_url' => ['nullable', 'string', 'max:2000'],
            'fit.scale' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'fit.rotation' => ['required', 'array:0,1,2', 'size:3'],
            'fit.rotation.*' => ['required', 'numeric', 'between:-360,360'],
            'fit.position' => ['required', 'array:0,1,2', 'size:3'],
            'fit.position.*' => $axis,
            'fit.stretch' => ['required', 'array:0,1,2', 'size:3'],
            'fit.stretch.*' => ['required', 'numeric', 'gt:0', 'max:10'],
        ];
    }

    /**
     * The validated values in the shape the shoes table stores. A stretch of
     * 1 on every axis is left out, as the shoe catalog does.
     *
     * @return array{name: string, is_visible: bool, fit: array{scale: float, rotation: list<float>, position: list<float>, stretch?: list<float>}}
     */
    public function shoeAttributes(): array
    {
        $toFloats = fn (array $values): array => array_map('floatval', array_values($values));
        $fit = $this->validated('fit');

        $attributes = [
            'name' => $this->validated('name'),
            'is_visible' => $this->boolean('is_visible'),
            'fit' => [
                'scale' => (float) $fit['scale'],
                'rotation' => $toFloats($fit['rotation']),
                'position' => $toFloats($fit['position']),
            ],
        ];

        $stretch = $toFloats($fit['stretch']);

        if ($stretch !== [1.0, 1.0, 1.0]) {
            $attributes['fit']['stretch'] = $stretch;
        }

        return $attributes;
    }

    /**
     * The library page the admin came from (keeping its page, filter and
     * search), or the first page.
     */
    public function libraryUrl(): string
    {
        return static::safeLibraryUrl($this->input('return_url'));
    }

    /**
     * Only URLs of the library page itself are followed, so the form cannot
     * be used to redirect to another site.
     */
    public static function safeLibraryUrl(?string $url): string
    {
        $libraryUrl = route('admin.shoes.index');

        if ($url !== null && ($url === $libraryUrl || Str::startsWith($url, $libraryUrl.'?'))) {
            return $url;
        }

        return $libraryUrl;
    }
}
