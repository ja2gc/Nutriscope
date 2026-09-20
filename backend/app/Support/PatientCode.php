<?php

namespace App\Support;

use App\Models\Patient;

final class PatientCode
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        do {
            $code = 'NS-'.self::segment().'-'.self::segment();
        } while (Patient::query()->where('patient_code', $code)->exists());

        return $code;
    }

    private static function segment(): string
    {
        $segment = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < 4; $index++) {
            $segment .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $segment;
    }
}
