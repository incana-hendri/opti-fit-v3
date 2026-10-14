<?php

namespace App\Enums;

// BR-2.3
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<string, string> value => label, for selects
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $gender) => [$gender->value => $gender->label()])->all();
    }
}
