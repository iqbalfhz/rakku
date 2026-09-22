<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

/**
 * Input nominal rupiah (tanpa sen) yang seragam di seluruh form.
 */
class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->numeric()
            ->prefix('Rp')
            ->minValue(0)
            ->maxValue(9_999_999_999_999)
            ->step(1);
    }
}
