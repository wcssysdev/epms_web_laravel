<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role_level <= 30;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'user_name'                    => 'required|string|max:150',
            'username'                     => "required|string|max:100|unique:tc_user,username,{$userId}",
            'email'                        => "nullable|email|max:150|unique:tc_user,email,{$userId}",
            'role_id'                      => 'required|exists:m_roles,id',
            'user_employee_code'           => "nullable|string|max:100|unique:tc_user,user_employee_code,{$userId}",
            'user_internal_employee_code'  => 'nullable|string|max:100',
            'is_active'                    => 'boolean',
        ];
    }
}
