<?php

namespace App\Filament\Actions;

use App\Actions\ReplyToSupportTicket;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

class ReplyToTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'replyToTicket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Balas')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->visible(fn (SupportTicket $record): bool => ! $record->isClosed())
            ->schema([
                Textarea::make('body')
                    ->label('Balasan')
                    ->rows(5)
                    ->required()
                    ->maxLength(2000),
                FileUpload::make('attachment_path')
                    ->label('Lampiran (opsional)')
                    ->image()
                    ->disk(SupportMessage::attachmentDisk())
                    ->directory(SupportMessage::ATTACHMENT_DIRECTORY)
                    ->visibility('private')
                    ->maxSize(5120)
                    ->openable(),
            ])
            ->action(function (SupportTicket $record, array $data, $livewire): void {
                app(ReplyToSupportTicket::class)->handle($record, auth()->user(), $data);

                $livewire->refreshTicketThread();

                $this->successNotificationTitle('Balasan terkirim');
                $this->success();
            });
    }
}
