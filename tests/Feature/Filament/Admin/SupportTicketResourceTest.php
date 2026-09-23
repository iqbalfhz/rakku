<?php

use App\Enums\SupportTicketStatus;
use App\Filament\Admin\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Admin\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Admin\Widgets\OpenTicketsTable;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReplied;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('answers a ticket and notifies its owner', function () {
    Notification::fake();
    $member = User::factory()->create();
    $ticket = SupportTicket::factory()->for($member)->create();
    SupportMessage::factory()->for($ticket, 'ticket')->for($member, 'author')->create();

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction(TestAction::make('replyToTicket'), data: ['body' => 'Sudah kami perbaiki, mohon dicek lagi.'])
        ->assertHasNoActionErrors();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Answered)
        ->and($ticket->messages()->reorder()->latest('id')->first())
        ->body->toBe('Sudah kami perbaiki, mohon dicek lagi.')
        ->user_id->toBe($this->admin->id);

    Notification::assertSentTo(
        $member,
        SupportTicketReplied::class,
        fn (SupportTicketReplied $notification, array $channels): bool => $channels === ['database', 'mail'],
    );
});

it('needs an answer before it can be sent', function () {
    $ticket = SupportTicket::factory()->create();

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction(TestAction::make('replyToTicket'), data: ['body' => ''])
        ->assertHasActionErrors(['body' => 'required']);

    expect($ticket->messages()->count())->toBe(0);
});

it('closes a ticket and stops the conversation', function () {
    $ticket = SupportTicket::factory()->answered()->create();

    Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction(TestAction::make('closeTicket'))
        ->assertActionHidden(TestAction::make('replyToTicket'));

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Closed);
});

it('lists tickets that wait for an answer first', function () {
    $openTicket = SupportTicket::factory()->create();
    $closedTicket = SupportTicket::factory()->closed()->create();

    Livewire::test(ListSupportTickets::class)
        ->assertCanSeeTableRecords([$openTicket])
        ->assertCanNotSeeTableRecords([$closedTicket]);
});

it('picks up a ticket that arrives while the list is open', function () {
    $component = Livewire::test(ListSupportTickets::class);

    $newTicket = SupportTicket::factory()->create();

    $component->call('$refresh')->assertCanSeeTableRecords([$newTicket]);
});

it('picks up a reply that arrives while the thread is open', function () {
    $ticket = SupportTicket::factory()->create();
    $component = Livewire::test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()]);

    SupportMessage::factory()->for($ticket, 'ticket')->create(['body' => 'Pesan susulan dari pengguna.']);

    $component->call('$refresh')->assertSee('Pesan susulan dari pengguna.');
});

it('counts unanswered tickets in the admin menu badge', function () {
    SupportTicket::factory()->count(2)->create();
    SupportTicket::factory()->answered()->create();

    expect(SupportTicketResource::getNavigationBadge())->toBe('2');
});

it('puts unanswered tickets on the dashboard', function () {
    $openTicket = SupportTicket::factory()->create();
    $answeredTicket = SupportTicket::factory()->answered()->create();

    Livewire::test(OpenTicketsTable::class)
        ->assertCanSeeTableRecords([$openTicket])
        ->assertCanNotSeeTableRecords([$answeredTicket]);
});

it('keeps non-admin users out of the ticket list', function () {
    $this->actingAs(User::factory()->create())
        ->get(SupportTicketResource::getUrl('index'))
        ->assertForbidden();
});
