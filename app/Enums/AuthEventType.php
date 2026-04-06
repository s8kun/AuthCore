<?php

namespace App\Enums;

enum AuthEventType: string
{
    case RegistrationSucceeded = 'registration_succeeded';
    case RegistrationFailed = 'registration_failed';
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case LogoutSucceeded = 'logout_succeeded';
    case RefreshRotated = 'refresh_rotated';
    case OtpSent = 'otp_sent';
    case OtpResent = 'otp_resent';
    case OtpVerified = 'otp_verified';
    case OtpFailed = 'otp_failed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case GhostAccountCreated = 'ghost_account_created';
    case GhostAccountClaimed = 'ghost_account_claimed';
    case VerificationSent = 'verification_sent';
    case VerificationCompleted = 'verification_completed';
}
