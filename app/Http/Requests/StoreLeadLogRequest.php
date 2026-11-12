<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadLogRequest extends FormRequest
{
    /**
     * Authentication is handled by the AuthenticateLeadLogsToken middleware.
     */
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'offer_name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            // `present` rather than `required` so an empty array is accepted;
            // max caps the number of top-level keys to keep payloads bounded.
            'data' => ['present', 'array', 'max:200'],
        ];
    }
}
