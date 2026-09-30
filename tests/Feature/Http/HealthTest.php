<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `/up` bawaan Laravel sengaja tidak menyentuh apa pun — ia dipakai healthcheck
 * Docker, dan kalau ikut gagal saat database berkedip, container-nya di-restart
 * berulang justru saat keadaan sedang buruk. Rute ini menjawab pertanyaan yang
 * berbeda: bukan "PHP masih melayani?", melainkan "aplikasinya masih bisa dipakai?".
 */
it('says the app is well when everything answers', function () {
    $this->getJson(route('health'))
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('answers 503 when the database has stopped answering', function () {
    DB::shouldReceive('connection->select')->andThrow(new RuntimeException('server has gone away'));

    $this->getJson(route('health'))
        ->assertStatus(503)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('failing.0', 'database');
});

/**
 * Disk penuh tidak mematikan aplikasi, hanya membuatnya berhenti menerima foto
 * struk — kegagalan yang tidak pernah terlihat dari luar tanpa pemeriksaan ini.
 */
it('answers 503 when uploads can no longer be written', function () {
    Storage::shouldReceive('disk->put')->andThrow(new RuntimeException('no space left on device'));

    $this->getJson(route('health'))
        ->assertStatus(503)
        ->assertJsonPath('failing.0', 'storage');
});

it('keeps the docker healthcheck free of those checks', function () {
    DB::shouldReceive('connection->select')->andThrow(new RuntimeException('server has gone away'));

    $this->get('/up')->assertOk();
});
