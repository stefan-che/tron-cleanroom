<?php

namespace App\Casts;

use App\Helpers\StringHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;

class StripTagsFromJsonCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        $decodedValue = json_decode($value, true);

        if (is_array($decodedValue)) {
            return $this->processArray($decodedValue);
        }

        return $decodedValue;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        return json_encode($value);
    }

    private function processArray(array $array): Collection
    {
        return collect($array)->map(function ($item) {
            if (is_array($item)) {
                return $this->processArray($item);
            }

            if (is_string($item) && StringHelper::containsHtml($item)) {
                return strip_tags($item);
            }

            return $item;
        });
    }
}
