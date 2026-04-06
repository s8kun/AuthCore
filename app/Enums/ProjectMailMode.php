<?php

namespace App\Enums;

enum ProjectMailMode: string
{
    case Platform = 'platform';
    case CustomSmtp = 'custom_smtp';
}
