<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Models\Project;
use App\Services\ProjectUserFields\BuildProjectUserValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
        $rules = [
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

        $project = $this->resolveProject();

        if (! $project instanceof Project) {
            return $rules;
        }

        return [
            ...$rules,
            ...app(BuildProjectUserValidationRules::class)->for($project),
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

    /**
     * Configure post-validation hooks for project-scoped custom fields.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = $this->resolveProject();
                $customFields = $this->input('custom_fields');

                if (! $project instanceof Project || ! is_array($customFields)) {
                    return;
                }

                $allowedKeys = app(BuildProjectUserValidationRules::class)
                    ->for($project);

                $expectedKeys = collect(array_keys($allowedKeys))
                    ->filter(fn (string $key): bool => str_starts_with($key, 'custom_fields.'))
                    ->map(fn (string $key): string => Str::after($key, 'custom_fields.'))
                    ->values()
                    ->all();

                foreach (array_diff(array_keys($customFields), $expectedKeys) as $unexpectedKey) {
                    $validator->errors()->add(
                        "custom_fields.{$unexpectedKey}",
                        'This field is not defined for the current project.',
                    );
                }
            },
        ];
    }

    /**
     * Resolve the current project from the request attributes.
     */
    private function resolveProject(): ?Project
    {
        $project = $this->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        return $project instanceof Project ? $project : null;
    }
}
