<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('organizer can duplicate their event without ticket types', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'title' => '朝焼けトレッキング',
        'starts_at' => '2026-11-01 05:00:00',
        'ends_at' => '2026-11-01 09:00:00',
    ]);
    TicketType::factory()->for($event)->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::events.edit', ['event' => $event])
        ->call('duplicate')
        ->assertHasNoErrors();

    $duplicate = Event::where('id', '!=', $event->id)->sole();

    $component->assertRedirect(route('events.edit', $duplicate));

    expect($duplicate->user_id)->toBe($user->id);
    expect($duplicate->title)->toBe('朝焼けトレッキング（複製）');
    expect($duplicate->description)->toBe($event->description);
    expect($duplicate->venue)->toBe($event->venue);
    expect($duplicate->starts_at->toDateTimeString())->toBe('2026-11-08 05:00:00');
    expect($duplicate->ends_at->toDateTimeString())->toBe('2026-11-08 09:00:00');
    expect($duplicate->ticketTypes()->count())->toBe(0);

    expect($event->fresh()->title)->toBe('朝焼けトレッキング');
    expect($event->ticketTypes()->count())->toBe(1);
});

test('duplicated title is truncated to fit the max length', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create(['title' => str_repeat('あ', 100)]);

    $this->actingAs($user);

    Livewire::test('pages::events.edit', ['event' => $event])->call('duplicate');

    $duplicate = Event::where('id', '!=', $event->id)->sole();

    expect(mb_strlen($duplicate->title))->toBe(100);
    expect($duplicate->title)->toEndWith('（複製）');
});

test('duplicate action rejects a user who does not own the event', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner)->create();

    $component = Livewire::actingAs($owner)->test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component->call('duplicate')->assertForbidden();

    expect(Event::count())->toBe(1);
});

test('duplicate success message is shown on the edit page', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create();

    $this->actingAs($user)
        ->withSession(['status' => 'イベントを複製しました。'])
        ->get(route('events.edit', $event))
        ->assertSee('イベントを複製しました。');
});
