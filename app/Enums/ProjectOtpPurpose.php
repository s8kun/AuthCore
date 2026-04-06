<?php

namespace App\Enums;

enum ProjectOtpPurpose: string
{
    case RegisterVerify = 'register_verify';
    case LoginVerify = 'login_verify';
    case ForgotPassword = 'forgot_password';
    case GhostAccountClaim = 'ghost_account_claim';
    case EmailVerification = 'email_verification';
}
