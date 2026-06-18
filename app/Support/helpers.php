<?php

use App\Support\Hashid;

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
