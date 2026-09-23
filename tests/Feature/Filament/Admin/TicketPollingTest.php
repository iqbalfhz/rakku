<?php

use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the polling attribute on the ticket thread', function () {
    $ticket = SupportTicket::factory()->create();
    SupportMessage::factory()->for($ticket, 'ticket')->create();

    $this->get(SupportTicketResource::getUrl('view', ['record' => $ticket]))
        ->assertSuccessful()
        ->assertSee('wire:poll', escape: false);
});

it('puts admin messages on the right and user messages on the left', function () {
    $ticket = SupportTicket::factory()->create();
    SupportMessage::factory()->for($ticket, 'ticket')->create();
    SupportMessage::factory()->for($ticket, 'ticket')->fromAdmin()->create();

    $page = $this->get(SupportTicketResource::getUrl('view', ['record' => $ticket]))->assertSuccessful();

    expect($page->getContent())
        ->toContain('margin-right: auto')
        ->toContain('margin-left: auto');
});

it('renders the polling attribute on the ticket list', function () {
    SupportTicket::factory()->create();

    $this->get(SupportTicketResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('wire:poll', escape: false);
});
