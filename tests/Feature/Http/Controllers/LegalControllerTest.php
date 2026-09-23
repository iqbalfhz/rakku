<?php

use App\Models\User;

it('opens the legal pages for anyone, signed in or not', function (string $path, string $heading) {
    $this->get($path)
        ->assertSuccessful()
        ->assertSee($heading);

    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertSuccessful()
        ->assertSee($heading);
})->with([
    'kebijakan privasi' => ['/kebijakan-privasi', 'Kebijakan Privasi'],
    'syarat layanan' => ['/syarat-layanan', 'Syarat Layanan'],
]);

it('states the promises the app actually keeps', function () {
    $this->get('/kebijakan-privasi')
        ->assertSee('tidak menjual data Anda', escape: false)
        ->assertSee('30 hari')
        ->assertSee('90 hari');

    $this->get('/syarat-layanan')
        ->assertSee('bukan')
        ->assertSee('Tidak ada tagihan berulang');
});
