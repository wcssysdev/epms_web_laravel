<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role_level <= 30;
    }

    public function rules(): array
    {
        return [
            'new_password' => [
                'required', 'string', 'min:8', 'max:50', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+]).+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.regex' => 'Password must contain at least 1 uppercase, 1 lowercase, 1 number, and 1 special character.',
        ];
    }
}
