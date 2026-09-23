<?php

use App\Models\SubscriptionPayment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SubscriptionPaymentApproved;
use App\Notifications\SubscriptionPaymentRejected;
use App\Notifications\SubscriptionPaymentSubmitted;
use App\Notifications\SupportTicketOpened;
use App\Notifications\SupportTicketReplied;
use Filament\Facades\Filament;
use Illuminate\Notifications\Notification;

/**
 * Queue worker berjalan tanpa panel aktif, jadi isi email harus tetap bisa dibangun di sana.
 */
it('builds every notification email outside a Filament panel', function (callable $makeNotification) {
    $recipient = User::factory()->admin()->create();

    /** @var Notification $notification */
    $notification = $makeNotification();

    Filament::setCurrentPanel(null);

    expect((string) $notification->toMail($recipient)->render())->toContain(config('app.url'));
})->with([
    'pengajuan pembayaran' => [fn (): Notification => new SubscriptionPaymentSubmitted(SubscriptionPayment::factory()->create())],
    'pembayaran disetujui' => [fn (): Notification => new SubscriptionPaymentApproved(SubscriptionPayment::factory()->create(), now()->addMonth())],
    'pembayaran ditolak' => [fn (): Notification => new SubscriptionPaymentRejected(SubscriptionPayment::factory()->rejected()->create())],
    'tiket baru' => [fn (): Notification => new SupportTicketOpened(SupportTicket::factory()->create())],
    'balasan tiket' => [fn (): Notification => new SupportTicketReplied(SupportMessage::factory()->create())],
]);
