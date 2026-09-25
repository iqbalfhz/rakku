<?php

use App\Enums\SupportTicketStatus;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketOpened;
use App\Notifications\SupportTicketReplied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(SupportMessage::attachmentDisk());
    Notification::fake();

    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($this->user);
});

it('opens a ticket from the phone and wakes the admins', function () {
    $response = $this->postJson('/api/v1/support-tickets', [
        'subject' => 'Saldo tidak cocok',
        'body' => 'Setelah sinkron, saldo kas saya berkurang sendiri.',
    ])->assertCreated();

    $ticket = SupportTicket::query()->sole();

    expect($ticket->user_id)->toBe($this->user->id)
        ->and($ticket->ticket_number)->toStartWith('TKT-')
        ->and($ticket->messages)->toHaveCount(1)
        ->and($response->json('ticket.ticket_number'))->toBe($ticket->ticket_number);

    Notification::assertSentTo($this->admin, SupportTicketOpened::class);
});

it('takes a screenshot along with the complaint', function () {
    $this->postJson('/api/v1/support-tickets', [
        'subject' => 'Layar aneh',
        'body' => 'Begini tampilannya.',
        'attachment' => UploadedFile::fake()->image('layar.jpg'),
    ])->assertCreated();

    $message = SupportMessage::query()->sole();

    expect($message->attachment_path)->not->toBeNull();
    Storage::disk(SupportMessage::attachmentDisk())->assertExists($message->attachment_path);
});

it('shows the whole conversation, marking who said what', function () {
    $ticket = SupportTicket::factory()->for($this->user)->create();
    SupportMessage::factory()->for($ticket, 'ticket')->for($this->user, 'author')->create(['body' => 'Pertanyaan saya']);
    SupportMessage::factory()->for($ticket, 'ticket')->for($this->admin, 'author')->create(['body' => 'Jawaban admin']);

    $response = $this->getJson("/api/v1/support-tickets/{$ticket->ticket_number}")->assertSuccessful();

    expect($response->json('messages'))->toHaveCount(2)
        ->and($response->json('messages.0.body'))->toBe('Pertanyaan saya')
        ->and($response->json('messages.0.from_admin'))->toBeFalse()
        ->and($response->json('messages.1.from_admin'))->toBeTrue();
});

it('lets the user answer back and tells the admins', function () {
    $ticket = SupportTicket::factory()->for($this->user)->create(['status' => SupportTicketStatus::Answered]);

    $this->postJson("/api/v1/support-tickets/{$ticket->ticket_number}/messages", [
        'body' => 'Masih belum jalan.',
    ])->assertCreated();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->messages()->count())->toBe(1);

    Notification::assertSentTo($this->admin, SupportTicketReplied::class);
});

it('lists the tickets newest conversation first', function () {
    $older = SupportTicket::factory()->for($this->user)->create(['last_message_at' => now()->subDay()]);
    $newer = SupportTicket::factory()->for($this->user)->create(['last_message_at' => now()]);

    $response = $this->getJson('/api/v1/support-tickets')->assertSuccessful();

    expect($response->json('tickets.0.ticket_number'))->toBe($newer->ticket_number)
        ->and($response->json('tickets.1.ticket_number'))->toBe($older->ticket_number);
});

it('never shows one account the tickets of another', function () {
    $stranger = SupportTicket::factory()->for(User::factory())->create();

    $this->getJson('/api/v1/support-tickets')->assertSuccessful()
        ->assertJsonCount(0, 'tickets');

    $this->getJson("/api/v1/support-tickets/{$stranger->ticket_number}")->assertNotFound();
});

it('never lets one account answer in another conversation', function () {
    $stranger = SupportTicket::factory()->for(User::factory())->create();

    $this->postJson("/api/v1/support-tickets/{$stranger->ticket_number}/messages", ['body' => 'Menyelinap'])
        ->assertNotFound();

    expect($stranger->messages()->count())->toBe(0);
});

it('insists on a subject and a message', function () {
    $this->postJson('/api/v1/support-tickets', [])
        ->assertJsonValidationErrors(['subject', 'body']);
});

it('turns away a phone without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/support-tickets')->assertUnauthorized();
});
