<?php

namespace App\Helpers;

use App\Models\Wallet\Wallet;

class StringHelper
{

    public static function uniqueIdGenerate(): string
    {
        $letters = str_shuffle('abcdefghijklmnopqrstuvwxyz');
        $randomLetters = substr($letters, 0, 2);
        $numbers = random_int(100000, 999999);

        return $randomLetters . $numbers;
    }

    public static function generateRandomString($minLength, $maxLength): string
    {
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
        $length = random_int($minLength, $maxLength);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return $randomString;
    }

    public static function trim($value): string
    {
        $value = trim($value);
        $value = str_replace(['\r', '\t', '\xC2xA0'], ['', '', ' '], $value);
        return preg_replace('/(\s){3,}/', '$1$1', $value);
    }

    /**
     * Remove whitespace from a string
     * @param $value
     * @return string
     */
    public static function removeSpaces($value): string
    {
        $value = self::trim($value);
        return $value ? preg_replace('/\s+/', '', $value) : '';
    }

    /**
     * Remove digits from a string
     * @param $string
     * @return string
     */
    public static function removeInteger($string)
    {
        return trim(str_replace(range(0, 9), '', $string));
    }

    /**
     * Remove all non-digit characters from a string
     * @param $string
     * @return int
     */
    public static function onlyInt($string): int
    {
        $string = preg_replace('/[^\d,]/u', '', $string);
        return (int)str_replace(',', '.', $string);

        return preg_replace('/\D/', '', $string);
    }

    /**
     * Whether the string contains HTML
     * @param string $string
     * @return bool
     */
    public static function containsHtml(string $string): bool
    {
        return preg_match('/<[^>]*>/', $string) > 0;
    }

    /**
     * @param string $externalId
     * @return string
     */
    public static function getExternalId(string $externalId): string
    {
        return str_replace(['/', Wallet::PREFIX_EXTERNAL_ID], '', $externalId);
    }
}
