<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Jawaban untuk layanan pemantauan di luar server.
 *
 * Berbeda dari `/up` bawaan Laravel, yang sengaja tidak menyentuh apa pun: `/up`
 * dipakai healthcheck Docker, dan kalau ia ikut gagal saat database berkedip,
 * container-nya akan di-restart berulang justru saat keadaan sedang buruk.
 *
 * Yang di sini menjawab pertanyaan yang berbeda — bukan "PHP masih melayani?",
 * melainkan "aplikasinya masih bisa dipakai?". Server yang menjawab 200 sementara
 * databasenya mati adalah server yang mengaku sehat padahal tidak ada satu pun
 * pengguna bisa mencatat apa-apa.
 */
class HealthController extends Controller
{
    /**
     * Berkas percobaan yang ditulis lalu dihapus untuk memastikan penyimpanan
     * unggahan benar-benar bisa ditulisi, bukan sekadar ada.
     */
    private const string PROBE_FILE = 'health-probe.txt';

    public function __invoke(): JsonResponse
    {
        $failing = array_keys(array_filter([
            'database' => ! $this->databaseAnswers(),
            'storage' => ! $this->storageAccepts(),
        ]));

        return response()->json(
            $failing === [] ? ['ok' => true] : ['ok' => false, 'failing' => $failing],
            $failing === [] ? 200 : 503,
        );
    }

    private function databaseAnswers(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Disk penuh tidak membuat aplikasi mati, tapi membuatnya berhenti menerima
     * foto struk — dan kegagalan seperti itu tidak pernah terlihat dari luar.
     */
    private function storageAccepts(): bool
    {
        try {
            $disk = Storage::disk('local');

            $disk->put(self::PROBE_FILE, (string) now()->timestamp);
            $disk->delete(self::PROBE_FILE);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
