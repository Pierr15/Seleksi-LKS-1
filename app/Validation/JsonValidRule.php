<?php

namespace App\Validation;

class JsonValidRule
{
    /**
     * Nilai null/kosong dianggap lolos (gabungkan dengan permit_empty bila perlu).
     */
    public function json_valid($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            json_encode($value);

            return json_last_error() === JSON_ERROR_NONE;
        }

        if (! is_string($value)) {
            return false;
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
