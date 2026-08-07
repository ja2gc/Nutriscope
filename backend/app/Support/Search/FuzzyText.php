<?php

namespace App\Support\Search;

use Illuminate\Support\Str;

final class FuzzyText
{
    public static function normalize(?string $value): string
    {
        return Str::of($value ?? '')->lower()->squish()->toString();
    }

    public static function score(string $needle, string $value): ?int
    {
        $needle = self::normalize($needle);
        $value = self::normalize($value);
        if ($needle === '' || $value === '') {
            return null;
        }

        $score = 0;
        $valueWords = explode(' ', $value);
        foreach (explode(' ', $needle) as $needleWord) {
            $distance = min(array_map(fn (string $word): int => self::distance($needleWord, $word), $valueWords));
            if ($distance > (mb_strlen($needleWord) <= 7 ? 1 : 2)) {
                return null;
            }
            $score += $distance;
        }

        return $score;
    }

    private static function distance(string $left, string $right): int
    {
        $leftChars = mb_str_split($left);
        $rightChars = mb_str_split($right);
        if (count($leftChars) === count($rightChars)) {
            $mismatches = [];
            foreach ($leftChars as $i => $character) {
                if ($character !== $rightChars[$i]) {
                    $mismatches[] = $i;
                }
            }
            if (count($mismatches) === 2
                && $mismatches[1] === $mismatches[0] + 1
                && $leftChars[$mismatches[0]] === $rightChars[$mismatches[1]]
                && $leftChars[$mismatches[1]] === $rightChars[$mismatches[0]]) {
                return 1;
            }
        }

        return levenshtein($left, $right);
    }
}
