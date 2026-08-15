<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\CompanyController;
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
     * The other 14 child-CRM endpoint URLs are no longer collected here —
     * they're derived from `api_url` in {@see CompanyController::store()}
     * via {@see Company::deriveEndpointUrls()}. They remain individually
     * editable later through {@see CompanyUpdateRequest}.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:'.Company::class.',name'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'api_url' => ['required', 'string', 'max:255', 'url'],
            'api_key' => ['required', 'string', 'max:255', 'unique:'.Company::class.',api_key'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
