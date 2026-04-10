<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\BuildProjectUserValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
        $rules = [
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp_code' => ['required', 'string', 'min:4', 'max:12'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            ...$this->profileAttributeRules(),
        ];

        $project = $this->resolveProject();

        if (! $project instanceof Project) {
            return $rules;
        }

        return [
            ...$rules,
            ...app(BuildProjectUserValidationRules::class)->for($project, $this->resolveGhostAccount($project)),
        ];
    }

    /**
     * Get the custom validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.prohibited' => 'The first_name field is no longer built in. Define it as a project custom field and send it inside custom_fields.',
            'last_name.prohibited' => 'The last_name field is no longer built in. Define it as a project custom field and send it inside custom_fields.',
            'phone.prohibited' => 'The phone field is no longer built in. Define it as a project custom field and send it inside custom_fields.',
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
                    ->for($project, $this->resolveGhostAccount($project));

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

    /**
     * Resolve the ghost account for the submitted email address.
     */
    private function resolveGhostAccount(Project $project): ?ProjectUser
    {
        $email = $this->input('email');

        if (! is_string($email) || blank($email)) {
            return null;
        }

        return ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->where('is_ghost', true)
            ->first();
    }

    /**
     * Get the rules that prohibit legacy top-level profile attributes.
     *
     * @return array<string, array<int, string>>
     */
    private function profileAttributeRules(): array
    {
        return [
            'first_name' => ['prohibited'],
            'last_name' => ['prohibited'],
            'phone' => ['prohibited'],
        ];
    }
}
