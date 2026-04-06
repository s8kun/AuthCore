<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectOtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Http\Requests\Api\V1\Auth\ClaimGhostAccountRequest;
use App\Http\Requests\Api\V1\Auth\ForgotProjectPasswordRequest;
use App\Http\Requests\Api\V1\Auth\RefreshProjectUserTokenRequest;
use App\Http\Requests\Api\V1\Auth\ResendProjectOtpRequest;
use App\Http\Requests\Api\V1\Auth\ResetProjectPasswordRequest;
use App\Http\Requests\Api\V1\Auth\SendProjectOtpRequest;
use App\Http\Requests\Api\V1\Auth\StoreGhostAccountRequest;
use App\Http\Requests\Api\V1\Auth\VerifyProjectOtpRequest;
use App\Http\Requests\Api\V1\LoginProjectUserRequest;
use App\Http\Requests\Api\V1\RegisterProjectUserRequest;
use App\Http\Resources\ProjectAuthResource;
use App\Http\Resources\ProjectUserResource;
use App\Models\Project;
use App\Models\ProjectOtp;
use App\Models\ProjectUser;
use App\Services\Auth\GhostAccountService;
use App\Services\Auth\ProjectAuthService;
use App\Services\Auth\ProjectOtpService;
use App\Services\Auth\ProjectPasswordResetService;
use App\Services\Auth\ProjectTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly ProjectAuthService $projectAuthService,
        private readonly ProjectOtpService $projectOtpService,
        private readonly ProjectPasswordResetService $projectPasswordResetService,
        private readonly ProjectTokenService $projectTokenService,
        private readonly GhostAccountService $ghostAccountService,
    ) {}

    /**
     * Register a new project user.
     */
    public function register(RegisterProjectUserRequest $request): JsonResponse
    {
        $payload = $this->projectAuthService->register($this->resolveProject($request), $request->validated(), $request);

        return ProjectAuthResource::make($payload)
            ->response()
            ->setStatusCode(($payload['verification_required'] ?? false) === true ? Response::HTTP_ACCEPTED : Response::HTTP_CREATED);
    }

    /**
     * Authenticate a project user and issue an API token.
     */
    public function login(LoginProjectUserRequest $request): JsonResponse
    {
        return ProjectAuthResource::make(
            $this->projectAuthService->login($this->resolveProject($request), $request->validated(), $request)
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

        $this->projectAuthService->logout($projectUser, $request);

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully.',
            ],
        ]);
    }

    /**
     * Rotate a refresh token and issue a new access token pair.
     */
    public function refresh(RefreshProjectUserTokenRequest $request): JsonResponse
    {
        return ProjectAuthResource::make(
            $this->projectTokenService->rotateRefreshToken(
                $this->resolveProject($request),
                $request->validated('refresh_token'),
                $this->resolveDeviceName($request),
                $request,
            )
        )->response();
    }

    /**
     * Request a password reset email.
     */
    public function forgotPassword(ForgotProjectPasswordRequest $request): JsonResponse
    {
        $this->projectPasswordResetService->request(
            $this->resolveProject($request),
            $request->validated('email'),
            $request,
        );

        return response()->json([
            'data' => [
                'message' => 'If the request can be processed, an email will be sent.',
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Complete a password reset.
     */
    public function resetPassword(ResetProjectPasswordRequest $request): JsonResponse
    {
        $this->projectPasswordResetService->reset(
            $this->resolveProject($request),
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
            $request,
        );

        return response()->json([
            'data' => [
                'message' => 'Password reset successfully.',
            ],
        ]);
    }

    /**
     * Send an OTP for a project-scoped purpose.
     */
    public function sendOtp(SendProjectOtpRequest $request): JsonResponse
    {
        $project = $this->resolveProject($request);
        $email = $request->validated('email');

        $this->projectOtpService->send(
            $project,
            $email,
            $request->enum('purpose', ProjectOtpPurpose::class),
            $request,
            $this->findProjectUser($project, $email),
        );

        return response()->json([
            'data' => [
                'message' => 'If the request can be processed, an email will be sent.',
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Verify a project-scoped OTP.
     */
    public function verifyOtp(VerifyProjectOtpRequest $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        $otp = $this->projectOtpService->verify(
            $project,
            $request->validated('email'),
            $request->validated('otp_code'),
            $request->enum('purpose', ProjectOtpPurpose::class),
            $request,
        );

        if ($otp instanceof ProjectOtp && $otp->purpose === ProjectOtpPurpose::EmailVerification && $otp->projectUser instanceof ProjectUser) {
            $this->projectAuthService->completeEmailVerification($otp->projectUser, $request);
        }

        return response()->json([
            'data' => [
                'verified' => true,
                'message' => 'OTP verified successfully.',
            ],
        ]);
    }

    /**
     * Resend a project-scoped OTP.
     */
    public function resendOtp(ResendProjectOtpRequest $request): JsonResponse
    {
        $project = $this->resolveProject($request);
        $email = $request->validated('email');

        $this->projectOtpService->send(
            $project,
            $email,
            $request->enum('purpose', ProjectOtpPurpose::class),
            $request,
            $this->findProjectUser($project, $email),
            [],
            true,
        );

        return response()->json([
            'data' => [
                'message' => 'If the request can be processed, an email will be sent.',
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Create a ghost account within the current project.
     */
    public function storeGhostAccount(StoreGhostAccountRequest $request): JsonResponse
    {
        $ghostAccount = $this->ghostAccountService->create(
            $this->resolveProject($request),
            $request->validated(),
            $request,
        );

        return ProjectUserResource::make($ghostAccount)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Claim a ghost account and issue tokens.
     */
    public function claimGhostAccount(ClaimGhostAccountRequest $request): JsonResponse
    {
        return ProjectAuthResource::make(
            $this->ghostAccountService->claim(
                $this->resolveProject($request),
                $request->validated(),
                $request,
            )
        )->response();
    }

    /**
     * Resolve the current project from the request.
     */
    private function resolveProject(Request $request): Project
    {
        /** @var Project $project */
        $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        return $project;
    }

    /**
     * Resolve the device name that should be attached to a new token.
     */
    private function resolveDeviceName(Request $request): string
    {
        return Str::limit((string) ($request->userAgent() ?: 'project-api-client'), 255, '');
    }

    /**
     * Find a project user by project-scoped email.
     */
    private function findProjectUser(Project $project, string $email): ?ProjectUser
    {
        return ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->first();
    }
}
