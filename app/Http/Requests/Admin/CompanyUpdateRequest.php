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
                'nullable',
                'string',
                'max:255',
                Rule::unique(Company::class, 'api_key')->ignore($this->route('company')?->id),
            ],
            'leads_count_url' => ['nullable', 'string', 'max:255', 'url'],
            'affiliates_url' => ['nullable', 'string', 'max:255', 'url'],
            'advertisers_url' => ['nullable', 'string', 'max:255', 'url'],
            'affiliate_count_api_url' => ['nullable', 'string', 'max:255', 'url'],
            'advertiser_count_api_url' => ['nullable', 'string', 'max:255', 'url'],
            'send_test_lead_url' => ['nullable', 'string', 'max:255', 'url'],
            'release_ftd_url' => ['nullable', 'string', 'max:255', 'url'],
            'send_lead_url' => ['nullable', 'string', 'max:255', 'url'],
            'update_affiliate_status_url' => ['nullable', 'string', 'max:255', 'url'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
