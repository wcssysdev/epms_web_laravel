<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role_level <= 30;
    }

    public function rules(): array
    {
        return [
            'user_name'                    => 'required|string|max:150',
            'username'                     => 'required|string|max:100|unique:tc_user,username',
            'email'                        => 'nullable|email|max:150|unique:tc_user,email',
            'password'                     => [
                'required', 'string', 'min:8', 'max:50', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+]).+$/',
            ],
            'role_id'                      => 'required|exists:m_roles,id',
            'user_employee_code'           => 'nullable|string|max:100|unique:tc_user,user_employee_code',
            'user_internal_employee_code'  => 'nullable|string|max:100',
            'is_active'                    => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least 1 uppercase, 1 lowercase, 1 number, and 1 special character.',
        ];
    }
}
