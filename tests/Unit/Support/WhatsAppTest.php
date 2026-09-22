<?php

use App\Support\WhatsApp;

it('normalizes Indonesian phone numbers to the international format', function (string $phone, string $expected) {
    expect(WhatsApp::normalizePhone($phone))->toBe($expected);
})->with([
    'local with leading zero' => ['0812-3456-7890', '6281234567890'],
    'international with plus and spaces' => ['+62 812 3456 7890', '6281234567890'],
    'already international' => ['6281234567890', '6281234567890'],
    'without leading zero' => ['81234567890', '6281234567890'],
]);

it('builds a wa.me link with the encoded message', function () {
    expect(WhatsApp::chatUrl('0812 3456 7890', "Halo & salam\nTotal: Rp 80.000"))
        ->toBe('https://wa.me/6281234567890?text=Halo%20%26%20salam%0ATotal%3A%20Rp%2080.000');
});
