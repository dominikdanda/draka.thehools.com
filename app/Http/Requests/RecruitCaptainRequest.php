<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecruitCaptainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A[\p{L}\p{N}][\p{L}\p{M}\p{N} .'\-]*\z/u"],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Podaj imię kapitana.',
            'name.string' => 'Imię kapitana musi być tekstem.',
            'name.min' => 'Imię kapitana musi mieć co najmniej 2 znaki.',
            'name.max' => 'Imię kapitana może mieć najwyżej 50 znaków.',
            'name.regex' => 'Imię musi zaczynać się literą lub cyfrą i może zawierać litery, cyfry, spacje, kropki, apostrofy i myślniki.',
        ];
    }
}
