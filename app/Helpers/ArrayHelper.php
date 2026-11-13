<?php

namespace App\Helpers;

class ArrayHelper
{

    /**
     * @param array $arr
     * @param string $delimetr
     * @return string
     */
    public static function implodeWithKeys(array $arr, string $delimetr = ','): string
    {
        array_walk($arr, static function (&$value, $key) {
            $value = "$key: $value";
        });

        return implode($delimetr, $arr);
    }

    /**
     * Get array values by several keys
     * @param array $arr
     * @param array $keys
     * @return array
     */
    public static function getValuesByKeys(array $arr, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($arr[$key])) {
                $result[] = $arr[$key];
            }
        }
        return $result;
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function hash(array $arr): string
    {
        return md5(serialize($arr));
    }

    /**
     * Splits a string by several delimiters and trims whitespace from the values
     * @param string $string
     * @param array|string $delimiters Delimiter or array of delimiters
     * @return array
     */
    public static function explode(string $string, array|string $delimiters): array
    {
        if (!$string) {
            return [];
        }

        if (!is_array($delimiters)) {
            $delimiters = [$delimiters];
        }

        $firstDelimiter = $delimiters[0];
        foreach ($delimiters as $delimiter) {
            if ($delimiter !== $firstDelimiter) {
                $string = str_replace($delimiter, $firstDelimiter, $string);
            }
        }

        $result = array_map('trim', explode($firstDelimiter, $string));

        return array_filter($result, static function($value) {
            return $value !== '';
        });
    }
}
