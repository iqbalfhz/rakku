<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Halaman kebijakan privasi dan syarat layanan, terbuka untuk siapa saja.
 */
class LegalController extends Controller
{
    /**
     * Tanggal berlakunya ketentuan; diperbarui manual saat isinya berubah.
     */
    private const string UPDATED_AT = '23 September 2026';

    public function privacy(): View
    {
        return view('legal.privacy', $this->pageData());
    }

    public function terms(): View
    {
        return view('legal.terms', $this->pageData());
    }

    /**
     * @return array<string, string>
     */
    private function pageData(): array
    {
        return [
            'updatedAt' => self::UPDATED_AT,
            'loginUrl' => filament()->getPanel('app')->getLoginUrl(),
            'registerUrl' => filament()->getPanel('app')->getRegistrationUrl(),
        ];
    }
}
