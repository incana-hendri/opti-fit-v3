<?php

namespace App\Support;

/**
 * Phone numbers are stored as entered and validated loosely: digits, +, spaces (docs/03 § users).
 */
final class Phone
{
    /**
     * @return list<string>
     */
    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,19}$/'];
    }
}
