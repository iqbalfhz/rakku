<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email : Email user yang sudah terdaftar}')]
#[Description('Beri akses panel admin ke user yang sudah terdaftar (untuk admin pertama di server)')]
class MakeAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("User dengan email {$this->argument('email')} tidak ditemukan. Daftar dulu lewat /app/register.");

            return self::FAILURE;
        }

        $user->is_admin = true;
        $user->save();

        $this->verifyEmail($user);

        $this->info("{$user->name} sekarang bisa membuka panel admin.");

        return self::SUCCESS;
    }

    /**
     * Admin pertama di server baru biasanya belum menerima email verifikasi karena SMTP belum siap.
     */
    private function verifyEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        $this->info("Email {$user->email} ditandai terverifikasi.");
    }
}
