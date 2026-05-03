<?php

use App\Models\Cycle;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\ApnsChannel;
use App\Services\Push\FcmChannel;
use App\Services\Push\PushChannel;
use App\Services\Push\PushDispatcher;
use App\Services\Push\WebPushChannel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 一個 fake channel 紀錄收到的 send call，並可預設 send() 回傳值。
 */
function fakeChannel(array $stub = ['ok' => true, 'status' => 200, 'reason' => null]): PushChannel
{
    return new class($stub) implements PushChannel
    {
        public array $calls = [];

        public function __construct(public array $stub) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(PushSubscription $sub, string $title, string $body, array $data = []): array
        {
            $this->calls[] = compact('sub', 'title', 'body', 'data');

            return $this->stub;
        }
    };
}

beforeEach(function () {
    Cache::flush();
});

it('dispatches to webpush channel when sub.platform = web', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://fcm.example/abc',
        'p256dh' => 'pk',
        'auth' => 'auth',
        'platform' => 'web',
    ]);

    $web = fakeChannel();
    $fcm = fakeChannel(['ok' => false, 'status' => 500, 'reason' => 'should-not-be-called']);
    $apns = fakeChannel(['ok' => false, 'status' => 500, 'reason' => 'should-not-be-called']);

    $dispatcher = new PushDispatcher($fcm, $apns, $web);
    $r = $dispatcher->dispatch($sub, 'hi', 'body');

    expect($r['ok'])->toBeTrue()
        ->and($r['channel'])->toBe('web')
        ->and($web->calls)->toHaveCount(1)
        ->and($fcm->calls)->toHaveCount(0)
        ->and($apns->calls)->toHaveCount(0);
});

it('dispatches to fcm channel when sub.platform = android', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'android',
        'device_token' => 'fcm-token-xxx',
    ]);

    $web = fakeChannel();
    $fcm = fakeChannel();
    $apns = fakeChannel();

    $dispatcher = new PushDispatcher($fcm, $apns, $web);
    $r = $dispatcher->dispatch($sub, 'hi', 'body');

    expect($r['ok'])->toBeTrue()
        ->and($r['channel'])->toBe('android')
        ->and($fcm->calls)->toHaveCount(1)
        ->and($web->calls)->toHaveCount(0);
});

it('dispatches to apns channel when sub.platform = ios', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'ios',
        'device_token' => 'apns-token-xxx',
    ]);

    $web = fakeChannel();
    $fcm = fakeChannel();
    $apns = fakeChannel();

    $dispatcher = new PushDispatcher($fcm, $apns, $web);
    $r = $dispatcher->dispatch($sub, 'hi', 'body');

    expect($r['ok'])->toBeTrue()
        ->and($r['channel'])->toBe('ios')
        ->and($apns->calls)->toHaveCount(1);
});

it('purges subscription when channel returns 410', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'android',
        'device_token' => 'expired-token',
    ]);

    $fcm = fakeChannel(['ok' => false, 'status' => 410, 'reason' => 'Gone']);
    $dispatcher = new PushDispatcher($fcm, fakeChannel(), fakeChannel());

    $r = $dispatcher->dispatch($sub, 'hi', 'body');

    expect($r['ok'])->toBeFalse();
    expect(PushSubscription::find($sub->id))->toBeNull();
});

it('purges subscription when reason contains unregistered', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'ios',
        'device_token' => 'bad',
    ]);

    $apns = fakeChannel(['ok' => false, 'status' => 400, 'reason' => 'BadDeviceToken unregistered']);
    $dispatcher = new PushDispatcher(fakeChannel(), $apns, fakeChannel());

    $dispatcher->dispatch($sub, 't', 'b');

    expect(PushSubscription::find($sub->id))->toBeNull();
});

it('does not purge subscription when channel is just not_configured', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'android',
        'device_token' => 'tok',
    ]);

    $fcm = fakeChannel(['ok' => false, 'status' => null, 'reason' => 'not_configured']);
    $dispatcher = new PushDispatcher($fcm, fakeChannel(), fakeChannel());

    $dispatcher->dispatch($sub, 't', 'b');

    expect(PushSubscription::find($sub->id))->not->toBeNull();
});

it('records success metric in cache on ok send', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    $sub = PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'web',
        'endpoint' => 'https://e/1',
        'p256dh' => 'p',
        'auth' => 'a',
    ]);

    $dispatcher = new PushDispatcher(fakeChannel(), fakeChannel(), fakeChannel());
    $dispatcher->dispatch($sub, 't', 'b');

    expect((int) Cache::get(config('push.metrics.success_key')))->toBe(1);
});

it('PushSendDailyReminders calls dispatcher for opt-in users', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'web',
        'endpoint' => 'https://e/x',
        'p256dh' => 'p',
        'auth' => 'a',
    ]);

    // Trigger period_eta_minus_1：latest start = 27 天前，cycle=28 → 預測明天經期。
    // 需要 ≥2 cycle 才能 predict（樣本不足時 BodyRhythmCalculator 走 default 28）
    $today = CarbonImmutable::today();
    foreach ([83, 55, 27] as $daysAgo) {
        Cycle::create([
            'user_id' => $user->id,
            'start_date' => $today->subDays($daysAgo)->toDateString(),
            'end_date' => $today->subDays($daysAgo - 4)->toDateString(),
            'peak_flow' => 3,
        ]);
    }

    $web = fakeChannel();
    app()->instance(PushDispatcher::class, new PushDispatcher(fakeChannel(), fakeChannel(), $web));

    $this->artisan('push:send-daily-reminders')->assertSuccessful();

    expect(count($web->calls))->toBeGreaterThanOrEqual(1);
});

it('FcmChannel reports not_configured when env missing', function () {
    $ch = new FcmChannel('', '');
    expect($ch->isConfigured())->toBeFalse();

    $sub = PushSubscription::create([
        'user_id' => User::factory()->create()->id,
        'platform' => 'android',
        'device_token' => 't',
    ]);
    $r = $ch->send($sub, 'a', 'b');
    expect($r['ok'])->toBeFalse()
        ->and($r['reason'])->toBe('not_configured');
});

it('ApnsChannel reports not_configured when env missing', function () {
    $ch = new ApnsChannel('', '', '', '');
    expect($ch->isConfigured())->toBeFalse();

    $sub = PushSubscription::create([
        'user_id' => User::factory()->create()->id,
        'platform' => 'ios',
        'device_token' => 't',
    ]);
    $r = $ch->send($sub, 'a', 'b');
    expect($r['ok'])->toBeFalse()
        ->and($r['reason'])->toBe('not_configured');
});

it('WebPushChannel reports not_configured when VAPID missing', function () {
    $ch = new WebPushChannel('', '', '');
    expect($ch->isConfigured())->toBeFalse();
});

it('POST /me/push/test routes through dispatcher to all subs', function () {
    $user = User::factory()->create(['push_opted_in' => true]);
    PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'web',
        'endpoint' => 'https://e/1',
        'p256dh' => 'p',
        'auth' => 'a',
    ]);
    PushSubscription::create([
        'user_id' => $user->id,
        'platform' => 'ios',
        'device_token' => 'ios-tok',
    ]);

    $web = fakeChannel();
    $apns = fakeChannel();
    app()->instance(PushDispatcher::class, new PushDispatcher(fakeChannel(), $apns, $web));

    Sanctum::actingAs($user);
    $res = $this->postJson('/api/v1/me/push/test');

    $res->assertOk()
        ->assertJsonPath('data.count', 2);
    expect($web->calls)->toHaveCount(1)
        ->and($apns->calls)->toHaveCount(1);
});

it('GET /me/push/subscriptions lists current users subs only', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    PushSubscription::create([
        'user_id' => $u1->id, 'platform' => 'web',
        'endpoint' => 'https://e/u1', 'p256dh' => 'p', 'auth' => 'a',
    ]);
    PushSubscription::create([
        'user_id' => $u2->id, 'platform' => 'ios', 'device_token' => 'tk',
    ]);

    Sanctum::actingAs($u1);
    $res = $this->getJson('/api/v1/me/push/subscriptions');
    $res->assertOk();
    expect($res->json('data'))->toHaveCount(1)
        ->and($res->json('data.0.platform'))->toBe('web');
});

it('subscribe endpoint accepts native ios device_token', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $res = $this->postJson('/api/v1/me/push/subscribe', [
        'platform' => 'ios',
        'device_token' => 'apns-test-token',
    ]);

    $res->assertCreated()->assertJsonPath('data.platform', 'ios');
    expect(PushSubscription::where('user_id', $u->id)->where('platform', 'ios')->exists())->toBeTrue();
});
