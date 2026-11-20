<?php

use App\QuantityFormatter;

test('quantity display trims only fractional zeros without losing precision', function (string $stored, string $display) {
    expect(QuantityFormatter::format($stored))->toBe($display);
})->with([
    ['1.0000', '1'], ['0.0000', '0'], ['10.5000', '10.5'], ['8.2500', '8.25'],
    ['2.1234', '2.1234'], ['-2.2500', '-2.25'], ['1000', '1000'], ['1000.0000', '1000'],
    ['99999999999999.9999', '99999999999999.9999'], ['99999999999999.0001', '99999999999999.0001'],
    ['-0.0000', '0'], ['0.0001', '0.0001'],
]);
