<?php

namespace App;

final class QuantityFormatter
{
    public static function format(string $quantity): string
    {
        $display = str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;

        return $display === '-0' ? '0' : $display;
    }
}
