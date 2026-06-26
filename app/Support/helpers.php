<?php

use App\Support\Hashid;
use App\Support\SciText;
use App\Support\SignedImage;

if (! function_exists('v2_actor')) {
    /**
     * The currently authenticated V2 user across all five guards, as
     * ['id' => int, 'role' => string, 'guard' => string], or null.
     */
    function v2_actor(): ?array
    {
        $guards = [
            'v2_super_admin'  => 'super_admin',
            'v2_school_admin' => 'school_admin',
            'v2_branch_admin' => 'branch_admin',
            'v2_teacher'      => 'teacher',
            'v2_student'      => 'student',
        ];
        foreach ($guards as $guard => $role) {
            // check() lazily resolves from the session - hasUser() only sees a
            // user already eagerly loaded by an auth: middleware (absent here).
            if (auth()->guard($guard)->check()) {
                return ['id' => auth()->guard($guard)->id(), 'role' => $role, 'guard' => $guard];
            }
        }

        return null;
    }
}

if (! function_exists('simg')) {
    /**
     * Signed, viewer-bound, expiring URL for a question image path. Use this in
     * place of asset('storage/'.$path) everywhere a question/option/diagram crop
     * is shown. $ctx may carry exam_id / question_id / ttl.
     */
    function simg(?string $path, array $ctx = []): string
    {
        return $path ? SignedImage::url($path, $ctx) : '';
    }
}

if (! function_exists('hid')) {
    /** Encode an integer id to its short URL code. */
    function hid(int|string|null $id): string
    {
        return $id === null || $id === '' ? '' : Hashid::encode($id);
    }
}

if (! function_exists('unhid')) {
    /** Decode a URL code back to its integer id (null if invalid). */
    function unhid(?string $code): ?int
    {
        return Hashid::decode($code);
    }
}

if (! function_exists('sci')) {
    /**
     * Render question/option text with scientific sub/superscripts as safe HTML
     * (use inside {!! !!}). Toggle: $enabled null => config('v2.sci_format');
     * pass false to force plain text (identical to the old {{ }} output), true
     * to force formatting on.
     */
    function sci(?string $text, ?bool $enabled = null): string
    {
        $enabled ??= (bool) config('v2.sci_format', true);

        return $enabled ? SciText::format($text) : e($text);
    }
}
