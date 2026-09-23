<?php

use App\Enums\SupportTicketStatus;
use App\Filament\App\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\App\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\App\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketOpened;
use App\Notifications\SupportTicketReplied;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('opens a ticket with a screenshot and alerts the admins', function () {
    Storage::fake('local');
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    actingInBook($user);

    Livewire::test(CreateSupportTicket::class)
        ->fillForm([
            'subject' => 'Saldo akun tidak cocok',
            'body' => 'Setelah transfer antar akun, saldo BCA saya berkurang dua kali.',
            'attachment_path' => UploadedFile::fake()->image('tangkapan-layar.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ticket = $user->supportTickets()->sole();
    $message = $ticket->messages()->sole();

    expect($ticket->subject)->toBe('Saldo akun tidak cocok')
        ->and($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->last_message_at)->not->toBeNull()
        ->and($message->body)->toBe('Setelah transfer antar akun, saldo BCA saya berkurang dua kali.')
        ->and(Storage::disk('local')->exists($message->attachment_path))->toBeTrue();

    Notification::assertSentTo(
        $admin,
        SupportTicketOpened::class,
        fn (SupportTicketOpened $notification, array $channels): bool => $channels === ['database', 'mail'],
    );
});

it('requires a subject and a message', function () {
    actingInBook(User::factory()->create());

    Livewire::test(CreateSupportTicket::class)
        ->fillForm(['subject' => '', 'body' => ''])
        ->call('create')
        ->assertHasFormErrors(['subject' => 'required', 'body' => 'required']);
});

it('shows the whole conversation, including the admin answer', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->for($user)->answered()->create();
    SupportMessage::factory()->for($ticket, 'ticket')->for($user, 'author')->create(['body' => 'Saldo saya tidak cocok.']);
    SupportMessage::factory()->for($ticket, 'ticket')->fromAdmin()->create(['body' => 'Sudah kami perbaiki, mohon dicek lagi.']);
    actingInBook($user);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->assertSee('Saldo saya tidak cocok.')
        ->assertSee('Sudah kami perbaiki, mohon dicek lagi.')
        ->assertSee('Sudah dijawab');
});

it('reopens the ticket when the user replies, and tells the admins', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->for($user)->answered()->create();
    actingInBook($user);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction(TestAction::make('replyToTicket'), data: ['body' => 'Masih belum cocok juga.'])
        ->assertHasNoActionErrors();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->messages()->latest('id')->value('body'))->toBe('Masih belum cocok juga.');

    Notification::assertSentTo($admin, SupportTicketReplied::class);
});

it('lets the user close a ticket and blocks further replies', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->for($user)->answered()->create();
    actingInBook($user);

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction(TestAction::make('closeTicket'))
        ->assertActionHidden(TestAction::make('replyToTicket'));

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Closed);
});

it('keeps tickets of other users out of reach', function () {
    $user = User::factory()->create();
    $foreignTicket = SupportTicket::factory()->create();
    actingInBook($user);

    Livewire::test(ListSupportTickets::class)
        ->assertCanNotSeeTableRecords([$foreignTicket]);

    $this->get(SupportTicketResource::getUrl('view', ['record' => $foreignTicket, 'tenant' => $user->books()->first()]))
        ->assertNotFound();
});
