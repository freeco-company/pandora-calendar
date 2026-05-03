<?php

use App\Models\PhotoJournalEntry;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    Storage::fake('local');
});

function makePremiumPj(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-pj-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

it('writes metadata only — no binary required to record an entry', function () {
    $res = $this->postJson('/api/v1/photo-journal', [
        'tag' => 'face',
        'captured_on' => now()->toDateString(),
        'cycle_day' => 14,
        'phase' => 'ovulation',
        'note' => '今天皮膚比較有光',
        'local_path' => 'capacitor://localhost/_capacitor_file_/photo/123.jpg',
    ]);

    $res->assertCreated()
        ->assertJsonPath('data.tag', 'face')
        ->assertJsonPath('data.cloud_synced', false)
        ->assertJsonPath('data.note', '今天皮膚比較有光');

    expect(PhotoJournalEntry::count())->toBe(1);
    expect(PhotoJournalEntry::first()->cloud_url)->toBeNull();
});

it('rejects invalid tag', function () {
    $this->postJson('/api/v1/photo-journal', [
        'tag' => 'evil',
        'captured_on' => now()->toDateString(),
    ])->assertStatus(422);
});

it('caps note at 500 chars', function () {
    $this->postJson('/api/v1/photo-journal', [
        'tag' => 'note',
        'captured_on' => now()->toDateString(),
        'note' => str_repeat('長', 501),
    ])->assertStatus(422);
});

it('lists entries by month and only own entries', function () {
    PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'face',
        'captured_on' => '2026-05-03',
    ]);
    PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'body',
        'captured_on' => '2026-05-15',
    ]);
    PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'face',
        'captured_on' => '2026-04-30', // 別月，不應出現
    ]);

    // 別人的 entry — 不可外洩
    $other = User::factory()->create();
    PhotoJournalEntry::create([
        'user_id' => $other->id, 'tag' => 'face',
        'captured_on' => '2026-05-10',
    ]);

    $res = $this->getJson('/api/v1/photo-journal/list?month=2026-05');

    $res->assertOk()
        ->assertJsonPath('data.month', '2026-05')
        ->assertJsonPath('data.count', 2);
});

it('refuses cloud upload for free users (premium gate)', function () {
    $entry = PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'face',
        'captured_on' => now()->toDateString(),
    ]);

    $res = $this->postJson("/api/v1/photo-journal/{$entry->id}/upload-cloud", [
        'photo' => UploadedFile::fake()->image('test.jpg', 200, 200),
    ]);

    $res->assertStatus(402)->assertJsonPath('error', 'premium_required');
    expect($entry->fresh()->cloud_synced)->toBeFalse();
});

it('allows cloud upload for premium users and stores encrypted', function () {
    makePremiumPj($this->user);
    $entry = PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'face',
        'captured_on' => now()->toDateString(),
    ]);

    $res = $this->postJson("/api/v1/photo-journal/{$entry->id}/upload-cloud", [
        'photo' => UploadedFile::fake()->image('test.jpg', 400, 400),
    ]);

    $res->assertOk()->assertJsonPath('data.cloud_synced', true);

    $entry->refresh();
    expect($entry->cloud_object_key)->not->toBeNull();
    expect(Storage::disk('local')->exists($entry->cloud_object_key))->toBeTrue();

    // 驗證 storage 內容是加密的（不是 raw image）
    $stored = Storage::disk('local')->get($entry->cloud_object_key);
    expect(strpos($stored, "\xff\xd8\xff"))->toBeFalse(); // 不是 JPEG magic bytes
});

it('cannot upload to another user entry (tenant boundary)', function () {
    makePremiumPj($this->user);
    $other = User::factory()->create();
    $foreign = PhotoJournalEntry::create([
        'user_id' => $other->id, 'tag' => 'face',
        'captured_on' => now()->toDateString(),
    ]);

    $this->postJson("/api/v1/photo-journal/{$foreign->id}/upload-cloud", [
        'photo' => UploadedFile::fake()->image('a.jpg'),
    ])->assertStatus(404); // findOwned → not found
});

it('destroy clears cloud copy + metadata', function () {
    makePremiumPj($this->user);
    $entry = PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'face',
        'captured_on' => now()->toDateString(),
    ]);
    $this->postJson("/api/v1/photo-journal/{$entry->id}/upload-cloud", [
        'photo' => UploadedFile::fake()->image('a.jpg'),
    ])->assertOk();

    $key = $entry->fresh()->cloud_object_key;
    expect(Storage::disk('local')->exists($key))->toBeTrue();

    $this->deleteJson("/api/v1/photo-journal/{$entry->id}")->assertOk();

    expect(PhotoJournalEntry::find($entry->id))->toBeNull();
    expect(Storage::disk('local')->exists($key))->toBeFalse();
});

it('cloud-only delete keeps metadata, removes binary', function () {
    makePremiumPj($this->user);
    $entry = PhotoJournalEntry::create([
        'user_id' => $this->user->id, 'tag' => 'body',
        'captured_on' => now()->toDateString(),
    ]);
    $this->postJson("/api/v1/photo-journal/{$entry->id}/upload-cloud", [
        'photo' => UploadedFile::fake()->image('b.jpg'),
    ])->assertOk();
    $key = $entry->fresh()->cloud_object_key;

    $this->deleteJson("/api/v1/photo-journal/{$entry->id}/cloud-only")
        ->assertOk()
        ->assertJsonPath('data.cloud_synced', false);

    expect(PhotoJournalEntry::find($entry->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($key))->toBeFalse();
});
