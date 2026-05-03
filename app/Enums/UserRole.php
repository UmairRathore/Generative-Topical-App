<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Admin = 'admin';                // school / institutional admin
    case SuperAdmin = 'super_admin';     // platform-wide (Generative Topical staff)
}
