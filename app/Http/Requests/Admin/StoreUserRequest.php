<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin family (super/country/company admin + Estate Admin=35) may manage users.
        return Auth::user()?->role_level <= 35;
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
            // Multi-scope grants (tc_user_scope)
            'scope_estates'                => 'nullable|array',
            'scope_estates.*'              => 'integer|exists:m_estate,id',
            'scope_countries'              => 'nullable|array',
            'scope_countries.*'            => 'integer|exists:m_country,id',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least 1 uppercase, 1 lowercase, 1 number, and 1 special character.',
        ];
    }
}
