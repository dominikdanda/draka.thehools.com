<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateSkillPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'stat' => ['required', Rule::in(['attack', 'defense', 'energy'])],
            'points' => ['required', 'integer', 'min:1'],
        ];
    }
}
