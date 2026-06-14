<?php

namespace App\Services\V2;

class TempPasswordGenerator
{
    // Omits visually ambiguous chars: 0, O, l, 1, I
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public function generate(int $length = 12): string
    {
        $chars    = self::CHARS;
        $max      = strlen($chars) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    public function generateBatch(int $count, int $length = 12): array
    {
        $passwords = [];
        for ($i = 0; $i < $count; $i++) {
            $passwords[] = $this->generate($length);
        }
        return $passwords;
    }
}
