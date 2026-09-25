<?php

use App\Models\User;
use App\Notifications\ServerErrorOccurred;
use App\Support\ServerErrorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Cache::flush();
    Notification::fake();

    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('tells the admins when the server throws something unexpected', function () {
    ServerErrorAlert::send(new RuntimeException('Basis data tidak bisa dihubungi'));

    Notification::assertSentTo($this->admin, ServerErrorOccurred::class);
});

it('says what broke and where', function () {
    ServerErrorAlert::send(new RuntimeException('Basis data tidak bisa dihubungi'));

    Notification::assertSentTo($this->admin, function (ServerErrorOccurred $notification): bool {
        return str_contains($notification->summary, 'Basis data tidak bisa dihubungi')
            && str_contains($notification->summary, 'RuntimeException')
            && str_contains($notification->location, 'ServerErrorAlertTest.php');
    });
});

it('does not send a hundred emails for one broken page', function () {
    foreach (range(1, 5) as $ignored) {
        ServerErrorAlert::send(new RuntimeException('Yang sama berulang'));
    }

    Notification::assertSentToTimes($this->admin, ServerErrorOccurred::class, 1);
});

it('speaks up again once the quiet hour has passed', function () {
    ServerErrorAlert::send(new RuntimeException('Yang sama berulang'));

    $this->travel(ServerErrorAlert::QUIET_MINUTES + 1)->minutes();

    ServerErrorAlert::send(new RuntimeException('Yang sama berulang'));

    Notification::assertSentToTimes($this->admin, ServerErrorOccurred::class, 2);
});

it('leaves ordinary people out of it', function () {
    $ordinary = User::factory()->create();

    ServerErrorAlert::send(new RuntimeException('Sesuatu meledak'));

    Notification::assertNothingSentTo($ordinary);
});

it('stays silent instead of breaking when it cannot reach anyone', function () {
    User::query()->delete();

    ServerErrorAlert::send(new RuntimeException('Sesuatu meledak'));

    Notification::assertNothingSent();
});
