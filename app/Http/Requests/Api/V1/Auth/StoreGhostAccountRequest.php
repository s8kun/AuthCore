<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreGhostAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'ghost_source' => ['nullable', 'string', 'max:255'],
            'must_set_password' => ['sometimes', 'boolean'],
            'must_verify_email' => ['sometimes', 'boolean'],
            'send_invite' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prepare the request for validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email,
            'first_name' => is_string($this->input('first_name')) ? trim((string) $this->input('first_name')) : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? trim((string) $this->input('last_name')) : $this->input('last_name'),
            'phone' => is_string($this->input('phone')) ? trim((string) $this->input('phone')) : $this->input('phone'),
        ]);
    }
}
