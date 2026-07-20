<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Company::class, 'name')->ignore($this->route('company')?->id),
            ],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'api_url' => ['required', 'string', 'max:255', 'url'],
            'api_key' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Company::class, 'api_key')->ignore($this->route('company')?->id),
            ],
            'leads_count_url' => ['nullable', 'string', 'max:255', 'url'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
