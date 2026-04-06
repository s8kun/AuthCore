<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Http\Requests\Api\V1\LoginProjectUserRequest;
use App\Http\Requests\Api\V1\RegisterProjectUserRequest;
use App\Http\Resources\ProjectAuthResource;
use App\Http\Resources\ProjectUserResource;
use App\Models\Project;
use App\Models\ProjectUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Register a new project user and issue an API token.
     */
    public function register(RegisterProjectUserRequest $request): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        $projectUser = $project->projectUsers()->create([
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'user',
        ]);

        return ProjectAuthResource::make(
            $this->issueAccessToken($projectUser, $this->resolveDeviceName($request))
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Authenticate a project user and issue an API token.
     *
     * @throws ValidationException
     */
    public function login(LoginProjectUserRequest $request): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        $projectUser = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $request->validated('email'))
            ->first();

        if (! $projectUser instanceof ProjectUser || ! Hash::check($request->validated('password'), $projectUser->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return ProjectAuthResource::make(
            $this->issueAccessToken($projectUser, $this->resolveDeviceName($request))
        )->response();
    }

    /**
     * Return the authenticated project user.
     */
    public function me(Request $request): ProjectUserResource
    {
        return ProjectUserResource::make($request->user());
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var ProjectUser $projectUser */
        $projectUser = $request->user();

        $currentAccessToken = $projectUser->currentAccessToken();

        if ($currentAccessToken !== null) {
            $projectUser->tokens()->whereKey($currentAccessToken->getKey())->delete();
        }

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully.',
            ],
        ]);
    }

    /**
     * Issue an access token for the given project user.
     *
     * @return array{expires_at: Carbon|null, plain_text_token: string, project_user: ProjectUser}
     */
    private function issueAccessToken(ProjectUser $projectUser, string $deviceName): array
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration === null ? null : now()->addMinutes((int) $expiration);
        $accessToken = $projectUser->createToken($deviceName, ['*'], $expiresAt);

        return [
            'expires_at' => $expiresAt,
            'plain_text_token' => $accessToken->plainTextToken,
            'project_user' => $projectUser,
        ];
    }

    /**
     * Resolve the device name that should be attached to a new token.
     */
    private function resolveDeviceName(Request $request): string
    {
        $deviceName = trim((string) $request->input('device_name', ''));

        if ($deviceName !== '') {
            return $deviceName;
        }

        return Str::limit((string) ($request->userAgent() ?: 'project-api-client'), 255, '');
    }
}
