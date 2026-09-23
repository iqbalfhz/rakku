<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

it('orders the sidebar from daily transactions down to book settings', function () {
    actingInBook(User::factory()->premium()->create());

    $navigation = collect(Filament::getNavigation())
        ->mapWithKeys(fn (NavigationGroup $group): array => [
            (string) $group->getLabel() => collect($group->getItems())
                ->map(fn (NavigationItem $item): string => $item->getLabel())
                ->values()
                ->all(),
        ])
        ->all();

    expect($navigation)->toBe([
        '' => ['Ringkasan', 'Langganan', 'Bantuan'],
        'Transaksi' => ['Transaksi', 'Transfer Antar Akun', 'Transaksi Berulang'],
        'Laporan & Anggaran' => ['Cash Flow', 'Laba-Rugi', 'Budget Bulanan'],
        'Utang & Invoice' => ['Utang-Piutang', 'Invoice', 'Klien'],
        'Pengaturan Buku' => ['Akun & Dompet', 'Kategori'],
    ]);
});

it('renders the one-group-at-a-time sidebar script', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get("/app/{$user->books()->first()->public_id}")
        ->assertSee('makeSidebarAccordion', escape: false);
});
