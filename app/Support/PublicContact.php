<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;

/**
 * Saluran kontak untuk calon pengguna yang belum bisa membuka tiket bantuan.
 */
class PublicContact
{
    public const string SETTING_KEY = 'contact.public';

    /**
     * @var list<string>
     */
    public const array FIELDS = ['whatsapp', 'email'];

    /**
     * @return array{whatsapp: string, email: string}
     */
    public static function all(): array
    {
        $stored = Arr::only((array) Setting::get(self::SETTING_KEY, []), self::FIELDS);

        return array_merge(config('contact'), array_filter($stored, 'filled'));
    }

    public static function whatsAppNumber(): ?string
    {
        return self::all()['whatsapp'] ?: null;
    }

    public static function email(): ?string
    {
        return self::all()['email'] ?: null;
    }

    /**
     * Link chat dengan pesan pembuka yang sudah terisi, atau null kalau nomornya belum diisi.
     */
    public static function whatsAppUrl(): ?string
    {
        $number = self::whatsAppNumber();

        return $number === null
            ? null
            : WhatsApp::chatUrl($number, 'Halo, saya ingin bertanya tentang RakKu.');
    }

    /**
     * @param  array<string, string|null>  $contact
     */
    public static function save(array $contact): void
    {
        Setting::put(self::SETTING_KEY, array_map(
            fn (?string $value): string => trim((string) $value),
            Arr::only($contact, self::FIELDS),
        ));
    }
}
