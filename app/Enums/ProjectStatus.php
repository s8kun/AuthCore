<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Suspended = 'suspended';
}
