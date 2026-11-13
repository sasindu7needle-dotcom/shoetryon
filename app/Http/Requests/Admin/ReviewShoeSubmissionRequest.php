<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewShoeSubmissionRequest extends FormRequest
{
    /**
     * What the admin checks on Tripo's 3D model before approving it. Every
     * check must pass to approve; any that fail are a reason to reject.
     *
     * @var array<string, string>
     */
    public const CHECKS = [
        'shape' => 'The shape and proportions match the photo',
        'sole' => 'The sole is complete, flat and in one piece',
        'colours' => 'The colours, materials and logos match the photo',
        'clean' => 'No holes, floating bits or extra geometry',
        'on_feet' => 'On my feet in the preview it looks like a real shoe at the right size',
    ];

    /**
     * Get the validation rules that apply to the request. Approving needs
     * every check ticked; rejecting needs a reason.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'checks' => ['array', 'required_if:decision,approve'],
            'checks.*' => [Rule::in(array_keys(self::CHECKS))],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ];
    }

    /**
     * Whether the admin chose to approve the model.
     */
    public function isApproval(): bool
    {
        return $this->input('decision') === 'approve';
    }

    /**
     * The checks the admin ticked, in the order they are listed.
     *
     * @return list<string>
     */
    public function passedChecks(): array
    {
        return array_values(array_intersect(array_keys(self::CHECKS), (array) $this->input('checks', [])));
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->isApproval() && ! $validator->errors()->has('checks') && count($this->passedChecks()) !== count(self::CHECKS)) {
                    $validator->errors()->add('checks', 'Tick every check before approving. If one fails, reject the model instead.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checks.required_if' => 'Tick every check before approving. If one fails, reject the model instead.',
            'note.required_if' => 'Say what is wrong with the model, so it can be fixed when the shoe is sent again.',
        ];
    }
}
