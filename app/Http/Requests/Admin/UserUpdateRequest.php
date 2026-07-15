<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignableRoles = $this->user()->hasRole('parent-admin')
            ? ['parent-admin', 'child-admin', 'agent']
            : ['child-admin', 'agent'];

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($this->route('user')->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::in($assignableRoles)],
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
        ];
    }
}
