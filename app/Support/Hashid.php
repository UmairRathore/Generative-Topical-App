<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| Hashid - reversible, non-sequential short codes for URL ids
|--------------------------------------------------------------------------
| A 40-bit balanced Feistel cipher (bijective, so collision-free for every id
| up to ~1.1e12) keyed by the app key, then base-54 encoded with an
| unambiguous alphabet. Produces short codes like "Mj3kP9". Self-contained -
| no package, no schema change. decode() is the exact inverse of encode().
*/
class Hashid
{
    private const ROUNDS = 4;
    private const HALF_BITS = 20;          // 20 + 20 = 40-bit id space
    private const MASK = 0xFFFFF;          // 2^20 - 1
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/l/I

    private static function f(int $round, int $value): int
    {
        return crc32($round.':'.$value.':'.config('app.key')) & self::MASK;
    }

    public static function encode(int|string $id): string
    {
        $id = (int) $id;
        if ($id < 0) {
            return self::ALPHABET[0];
        }

        $l = ($id >> self::HALF_BITS) & self::MASK;
        $r = $id & self::MASK;
        for ($i = 0; $i < self::ROUNDS; $i++) {
            $tmp = $r;
            $r = $l ^ self::f($i, $r);
            $l = $tmp;
        }

        return self::toBase(($l << self::HALF_BITS) | $r);
    }

    public static function decode(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        $n = self::fromBase($code);
        if ($n === null) {
            return null;
        }

        $l = ($n >> self::HALF_BITS) & self::MASK;
        $r = $n & self::MASK;
        for ($i = self::ROUNDS - 1; $i >= 0; $i--) {
            $tmp = $l;
            $l = $r ^ self::f($i, $l);
            $r = $tmp;
        }

        return ($l << self::HALF_BITS) | $r;
    }

    private static function toBase(int $n): string
    {
        $base = strlen(self::ALPHABET);
        if ($n === 0) {
            return self::ALPHABET[0];
        }
        $out = '';
        while ($n > 0) {
            $out = self::ALPHABET[$n % $base].$out;
            $n = intdiv($n, $base);
        }

        return $out;
    }

    private static function fromBase(string $code): ?int
    {
        $base = strlen(self::ALPHABET);
        $n = 0;
        for ($i = 0, $len = strlen($code); $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $code[$i]);
            if ($pos === false) {
                return null;
            }
            $n = $n * $base + $pos;
        }

        return $n;
    }
}
