<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\FakeBridge;
use Tests\TestCase;

/**
 * Di luar ponsel, panggilan ke jembatan NativePHP mencoba menghubungi relay Jump
 * dan menunggu sampai putus asa — satu panggilan bisa memakan dua detik. Fake
 * bridge bawaan NativePHP menangkapnya di dalam proses, jadi tes tetap cepat
 * dan isi panggilannya bisa diperiksa.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => FakeBridge::enable())
    ->in('Feature');
