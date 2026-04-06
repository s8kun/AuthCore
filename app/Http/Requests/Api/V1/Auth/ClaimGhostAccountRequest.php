<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ClaimGhostAccountRequest extends FormRequest
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
            'otp_code' => ['required', 'string', 'min:4', 'max:12'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the request for validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $otpCode = $this->input('otp_code');

        $this->merge([
            'email' => is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email,
            'otp_code' => is_string($otpCode) ? trim($otpCode) : $otpCode,
        ]);
    }
}
