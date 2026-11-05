<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectCaptainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['captain_id' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return [
            'captain_id.required' => 'Wybierz kapitana.',
            'captain_id.integer' => 'Wybierz prawidłowego kapitana.',
            'captain_id.min' => 'Wybierz prawidłowego kapitana.',
        ];
    }
}
