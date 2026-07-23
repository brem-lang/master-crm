<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:'.Company::class.',name'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'api_url' => ['required', 'string', 'max:255', 'url'],
            'api_key' => ['required', 'string', 'max:255', 'unique:'.Company::class.',api_key'],
            'leads_count_url' => ['nullable', 'string', 'max:255', 'url'],
            'affiliates_url' => ['nullable', 'string', 'max:255', 'url'],
            'advertisers_url' => ['nullable', 'string', 'max:255', 'url'],
            'affiliate_count_api_url' => ['nullable', 'string', 'max:255', 'url'],
            'advertiser_count_api_url' => ['nullable', 'string', 'max:255', 'url'],
            'send_test_lead_url' => ['nullable', 'string', 'max:255', 'url'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
