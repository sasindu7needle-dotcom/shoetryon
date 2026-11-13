<?php

namespace App\Http\Requests\N8n;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShoeModelRequest extends FormRequest
{
    /**
     * Largest GLB accepted from n8n, in kilobytes (100 MB).
     */
    public const MAX_GLB_KILOBYTES = 102400;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'glb' => ['required', 'file', 'max:'.self::MAX_GLB_KILOBYTES],
            'tripo_task_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * A GLB starts with the ASCII magic "glTF"; anything else is refused,
     * whatever its name or reported type.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $glb = $this->file('glb');

                if ($validator->errors()->has('glb') || $glb === null) {
                    return;
                }

                $handle = fopen($glb->getRealPath(), 'rb');
                $magic = $handle === false ? '' : (string) fread($handle, 4);

                if ($handle !== false) {
                    fclose($handle);
                }

                if ($magic !== 'glTF') {
                    $validator->errors()->add('glb', 'The file is not a GLB (binary glTF) model.');
                }
            },
        ];
    }
}
