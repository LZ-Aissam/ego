<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class IdentityKey
{
    public static function normalizeFirstName(string $firstName): string
    {
        return Str::lower(Str::squish(Str::ascii($firstName)));
    }

    public static function displayFirstName(string $firstName): string
    {
        return Str::squish($firstName);
    }

    public static function make(string $firstName, string $birthDate): string
    {
        return self::normalizeFirstName($firstName).'|'.Carbon::parse($birthDate)->format('Y-m-d');
    }
}
