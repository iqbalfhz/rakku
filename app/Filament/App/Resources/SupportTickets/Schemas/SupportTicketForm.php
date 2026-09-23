<?php

namespace App\Filament\App\Resources\SupportTickets\Schemas;

use App\Models\SupportMessage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subject')
                ->label('Judul')
                ->placeholder('Ringkas masalahnya dalam satu kalimat.')
                ->required()
                ->maxLength(120),
            Textarea::make('body')
                ->label('Ceritakan masalah atau pertanyaan Anda')
                ->placeholder('Sebutkan juga langkah yang Anda lakukan sebelum masalah muncul, kalau ada.')
                ->rows(6)
                ->required()
                ->maxLength(2000),
            FileUpload::make('attachment_path')
                ->label('Tangkapan layar (opsional)')
                ->helperText('Gambar maksimal 5 MB. Sangat membantu kalau yang Anda laporkan berupa tampilan yang keliru.')
                ->image()
                ->disk(SupportMessage::attachmentDisk())
                ->directory(SupportMessage::ATTACHMENT_DIRECTORY)
                ->visibility('private')
                ->maxSize(5120)
                ->openable(),
        ]);
    }
}
