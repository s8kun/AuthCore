<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterProjectUserRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];
        $email = $this->input('email');

        if ($this->exists('email')) {
            $payload['email'] = is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email;
        }

        foreach (['first_name', 'last_name', 'phone'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            $payload[$field] = is_string($value) ? trim($value) : $value;
        }

        $this->merge($payload);
    }
}
