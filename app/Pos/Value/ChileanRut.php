<?php

declare(strict_types=1);

namespace App\Pos\Value;

final class ChileanRut
{
    public static function normalize(string $input): ?string
    {
        $input = strtoupper(preg_replace('/\s+/u', '', $input) ?? '');
        if (preg_match('/^(\d{1,8}|\d{1,2}(?:\.\d{3}){1,2})-?([0-9K])$/D', $input, $parts) !== 1) {
            return null;
        }

        $number = ltrim(str_replace('.', '', $parts[1]), '0');
        if ($number === '') {
            return null;
        }

        $sum = 0;
        foreach (str_split(strrev($number)) as $position => $digit) {
            $sum += (int) $digit * (2 + $position % 6);
        }
        $check = (string) ((11 - $sum % 11) % 11);

        return ($check === '10' ? 'K' : $check) === $parts[2] ? $number.'-'.$parts[2] : null;
    }
}
