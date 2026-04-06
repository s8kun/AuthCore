<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        /** @var Project|null $project */
        $project = $this->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('project_users', 'email')->where(function (Builder $query) use ($project): void {
                    $query->where('project_id', $project?->getKey() ?? 0);
                }),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $deviceName = $this->input('device_name');

        $this->merge([
            'email' => is_string($email) ? Str::of($email)->trim()->lower()->toString() : $email,
            'device_name' => is_string($deviceName) ? trim($deviceName) : $deviceName,
        ]);
    }
}
