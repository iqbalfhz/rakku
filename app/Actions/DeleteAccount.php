<?php

namespace App\Actions;

use App\Models\SubscriptionPayment;
use App\Models\SupportMessage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Hapus akun beserta seluruh isinya: baris database ikut terhapus lewat relasi,
     * sedangkan berkas unggahan harus dihapus sendiri karena tersimpan di luar database.
     */
    public function handle(User $user): void
    {
        $files = $this->uploadedFiles($user);

        DB::transaction(fn () => $user->delete());

        foreach ($files as $disk => $paths) {
            Storage::disk($disk)->delete($paths);
        }
    }

    /**
     * Ketiga jenis berkas bisa memakai disk yang sama, jadi daftarnya digabung per disk.
     *
     * @return array<string, list<string>>
     */
    private function uploadedFiles(User $user): array
    {
        $bookIds = $user->books()->pluck('id');

        $groups = [
            [Transaction::receiptDisk(), Transaction::query()
                ->whereIn('book_id', $bookIds)
                ->whereNotNull('receipt_photo_path')
                ->pluck('receipt_photo_path')
                ->all()],
            [SubscriptionPayment::proofDisk(), $user->subscriptionPayments()
                ->pluck('proof_path')
                ->all()],
            [SupportMessage::attachmentDisk(), SupportMessage::query()
                ->whereIn('support_ticket_id', $user->supportTickets()->select('id'))
                ->whereNotNull('attachment_path')
                ->pluck('attachment_path')
                ->all()],
        ];

        $files = [];

        foreach ($groups as [$disk, $paths]) {
            $files[$disk] = [...($files[$disk] ?? []), ...$paths];
        }

        return $files;
    }
}
