<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Enums\ProjectEmailTemplateType;
use App\Enums\ProjectOtpPurpose;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectOtp;
use App\Models\ProjectUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProjectOtpService
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
        private readonly ProjectAuthRateLimiter $projectAuthRateLimiter,
        private readonly ProjectMailService $projectMailService,
    ) {}

    /**
     * Issue or resend an OTP for a project-scoped email and purpose.
     *
     * @param  array<string, mixed>  $meta
     */
    public function send(
        Project $project,
        string $email,
        ProjectOtpPurpose $purpose,
        Request $request,
        ?ProjectUser $projectUser = null,
        array $meta = [],
        bool $isResend = false,
        bool $ignoreCooldown = false,
    ): ProjectOtp {
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());

        if (! $settings->otp_enabled) {
            throw ValidationException::withMessages([
                'otp' => ['OTP is disabled for this project.'],
            ]);
        }

        $dailyLimitKey = "otp-daily|{$email}|{$purpose->value}";
        $secondsUntilEndOfDay = now()->diffInSeconds(now()->copy()->endOfDay());

        $this->projectAuthRateLimiter->ensureWithinLimit(
            $project,
            $dailyLimitKey,
            $settings->otp_daily_limit_per_email,
            max(60, $secondsUntilEndOfDay),
            null,
            'Too many OTP requests.',
        );

        $latestOtp = ProjectOtp::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('created_at')
            ->first();

        if (
            ! $ignoreCooldown
            && (
                $latestOtp instanceof ProjectOtp
                && $latestOtp->last_sent_at !== null
                && $latestOtp->last_sent_at->addSeconds($settings->otp_resend_cooldown_seconds)->isFuture()
            )
        ) {
            throw ValidationException::withMessages([
                'otp' => ['Please wait before requesting another code.'],
            ]);
        }

        $code = $this->generateCode($settings->otp_length);
        $expiresAt = now()->addMinutes($settings->otp_ttl_minutes);

        if ($latestOtp instanceof ProjectOtp && $latestOtp->consumed_at === null && $latestOtp->expires_at->isFuture()) {
            $latestOtp->forceFill([
                'project_user_id' => $projectUser?->getKey(),
                'code_hash' => Hash::make($code),
                'expires_at' => $expiresAt,
                'last_sent_at' => now(),
                'resend_count' => $isResend ? $latestOtp->resend_count + 1 : $latestOtp->resend_count,
                'meta' => $meta,
            ])->save();

            $otp = $latestOtp->refresh();
        } else {
            $otp = $project->otps()->create([
                'project_user_id' => $projectUser?->getKey(),
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => $expiresAt,
                'attempts' => 0,
                'max_attempts' => $settings->otp_max_attempts,
                'resend_count' => $isResend ? 1 : 0,
                'last_sent_at' => now(),
                'meta' => $meta,
            ]);
        }

        $this->projectMailService->queueTemplateEmail(
            $project,
            $email,
            $this->templateTypeForPurpose($purpose),
            [
                'user_email' => $email,
                'otp_code' => $code,
                'expires_in' => $this->formatMinutes($settings->otp_ttl_minutes),
            ],
        );

        $this->authEventLogger->log(
            $project,
            $isResend ? AuthEventType::OtpResent : AuthEventType::OtpSent,
            true,
            $request,
            $projectUser,
            $email,
            ['purpose' => $purpose->value],
        );

        return $otp;
    }

    /**
     * Verify an OTP for an email and purpose.
     */
    public function verify(
        Project $project,
        string $email,
        string $code,
        ProjectOtpPurpose $purpose,
        Request $request,
    ): ProjectOtp {
        /** @var ProjectOtp|null $otp */
        $otp = ProjectOtp::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('created_at')
            ->first();

        if (! $otp instanceof ProjectOtp || ! $otp->isUsable()) {
            $this->authEventLogger->log(
                $project,
                AuthEventType::OtpFailed,
                false,
                $request,
                null,
                $email,
                ['purpose' => $purpose->value, 'reason' => 'otp_missing_or_expired'],
            );

            throw ValidationException::withMessages([
                'otp_code' => ['The one-time code is invalid or expired.'],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            $this->authEventLogger->log(
                $project,
                AuthEventType::OtpFailed,
                false,
                $request,
                $otp->projectUser,
                $email,
                ['purpose' => $purpose->value, 'reason' => 'otp_mismatch'],
            );

            throw ValidationException::withMessages([
                'otp_code' => ['The one-time code is invalid or expired.'],
            ]);
        }

        $otp->forceFill([
            'consumed_at' => now(),
        ])->save();

        $this->authEventLogger->log(
            $project,
            AuthEventType::OtpVerified,
            true,
            $request,
            $otp->projectUser,
            $email,
            ['purpose' => $purpose->value],
        );

        return $otp;
    }

    /**
     * Generate a numeric OTP code.
     */
    private function generateCode(int $length): string
    {
        $code = '';

        while (strlen($code) < $length) {
            $code .= (string) random_int(0, 9);
        }

        return substr($code, 0, $length);
    }

    /**
     * Map OTP purposes to email template types.
     */
    private function templateTypeForPurpose(ProjectOtpPurpose $purpose): ProjectEmailTemplateType
    {
        return match ($purpose) {
            ProjectOtpPurpose::GhostAccountClaim => ProjectEmailTemplateType::GhostAccountInvite,
            ProjectOtpPurpose::EmailVerification => ProjectEmailTemplateType::EmailVerification,
            default => ProjectEmailTemplateType::Otp,
        };
    }

    /**
     * Format a minute count for human-readable emails.
     */
    private function formatMinutes(int $minutes): string
    {
        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
