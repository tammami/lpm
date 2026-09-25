<?php

namespace App\Enums;

/**
 * Helper untuk enum berlabel agar dapat dikirim ke frontend sebagai opsi select.
 */
trait HasOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
