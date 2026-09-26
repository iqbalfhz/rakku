<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\DeviceReports\DeviceReportResource;
use App\Models\DeviceReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari admin saat aplikasi ponsel seseorang rusak parah.
 *
 * Hanya untuk kerusakan yang membuat aplikasi tidak bisa dipakai; error biasa
 * cukup menumpuk di panel untuk dibaca saat sempat.
 */
class DeviceReportReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DeviceReport $report) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title('Aplikasi ponsel bermasalah')
            ->body($this->summary())
            ->actions([
                Action::make('review')
                    ->label('Lihat laporan')
                    ->url($this->reviewUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Aplikasi ponsel bermasalah di perangkat pengguna')
            ->greeting("Halo {$notifiable->name},")
            ->line($this->summary())
            ->line($this->report->message);

        if (($device = $this->deviceLine()) !== null) {
            $mail->line($device);
        }

        return $mail->action('Lihat laporan', $this->reviewUrl());
    }

    /**
     * Perangkat, versinya, dan alamat asalnya dalam satu baris.
     *
     * Kerusakan jarang mengenai semua orang sekaligus — biasanya satu merek atau
     * satu versi. Menaruhnya di email berarti polanya terbaca dari kotak masuk,
     * tanpa perlu membuka panel satu per satu.
     *
     * Laporan dari aplikasi versi lama membawa keterangan jauh lebih sedikit, jadi
     * barisnya dibangun dari apa pun yang ada dan dilewati kalau tidak ada apa-apa.
     */
    private function deviceLine(): ?string
    {
        $context = $this->report->context ?? [];

        $parts = array_filter([
            $context['device'] ?? null,
            $context['os'] ?? $context['platform'] ?? null,
            isset($context['app_version']) ? 'aplikasi '.$context['app_version'] : null,
            $this->report->ip_address,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function summary(): string
    {
        return sprintf(
            '%s (%s) melaporkan: %s.',
            $this->report->user->name,
            $this->report->user->email,
            $this->report->kind->getLabel(),
        );
    }

    private function reviewUrl(): string
    {
        return DeviceReportResource::getUrl('index', panel: 'admin');
    }
}
