<?php

namespace App\Enums;

enum QuestionVisibility: string
{
    case Public = 'public';
    case AdminOnly = 'admin_only';
    case Hidden = 'hidden';
}
