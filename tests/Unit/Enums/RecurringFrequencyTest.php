<?php

use App\Enums\RecurringFrequency;
use Carbon\CarbonImmutable;

it('calculates the next run date for each frequency', function (RecurringFrequency $frequency, string $current, string $anchor, string $expected) {
    $nextDate = $frequency->nextDate(CarbonImmutable::parse($current), CarbonImmutable::parse($anchor));

    expect($nextDate->toDateString())->toBe($expected);
})->with([
    'daily' => [RecurringFrequency::Daily, '2026-02-28', '2026-02-01', '2026-03-01'],
    'weekly' => [RecurringFrequency::Weekly, '2026-01-29', '2026-01-01', '2026-02-05'],
    'monthly clamps to the shorter month' => [RecurringFrequency::Monthly, '2026-01-31', '2026-01-31', '2026-02-28'],
    'monthly returns to the anchor day' => [RecurringFrequency::Monthly, '2026-02-28', '2026-01-31', '2026-03-31'],
    'yearly on a leap day' => [RecurringFrequency::Yearly, '2028-02-29', '2028-02-29', '2029-02-28'],
    'yearly back to leap day' => [RecurringFrequency::Yearly, '2031-02-28', '2028-02-29', '2032-02-29'],
]);
