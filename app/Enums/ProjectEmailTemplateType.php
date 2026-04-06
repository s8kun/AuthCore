<?php

namespace App\Enums;

enum ProjectEmailTemplateType: string
{
    case Otp = 'otp';
    case ForgotPassword = 'forgot_password';
    case ResetPasswordSuccess = 'reset_password_success';
    case Welcome = 'welcome';
    case EmailVerification = 'email_verification';
    case GhostAccountInvite = 'ghost_account_invite';
}
