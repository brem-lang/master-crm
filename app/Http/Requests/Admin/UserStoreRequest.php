<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
            ? ['parent-admin', 'child-admin', 'sales-rep']
            : ['child-admin', 'sales-rep'];

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in($assignableRoles)],
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
        ];
    }
}
