<?php

use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

test('organizer can create an event', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::events.create')
        ->set('title', '朝焼けトレッキング')
        ->set('description', '早朝の山を歩きます。')
        ->set('venue', '岩手山 馬返し登山口')
        ->set('starts_at', '2026-11-01T05:00')
        ->set('ends_at', '2026-11-01T09:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $event = Event::sole();

    expect($event->user_id)->toBe($user->id);
    expect($event->title)->toBe('朝焼けトレッキング');
    expect($event->venue)->toBe('岩手山 馬返し登山口');
});

test('event fields are required on create', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.create')
        ->call('save')
        ->assertHasErrors([
            'title' => 'required',
            'description' => 'required',
            'venue' => 'required',
            'starts_at' => 'required',
            'ends_at' => 'required',
        ]);

    expect(Event::count())->toBe(0);
});

test('end time must be after start time on create', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.create')
        ->set('title', '朝焼けトレッキング')
        ->set('description', '早朝の山を歩きます。')
        ->set('venue', '岩手山 馬返し登山口')
        ->set('starts_at', '2026-11-01T09:00')
        ->set('ends_at', '2026-11-01T05:00')
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect(Event::count())->toBe(0);
});

test('organizer can update their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('title', '更新後のタイトル')
        ->set('venue', '更新後の会場')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $event->refresh();

    expect($event->title)->toBe('更新後のタイトル');
    expect($event->venue)->toBe('更新後の会場');
});

test('end time must be after start time on update', function () {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('title', '更新後のタイトル')
        ->set('starts_at', '2026-11-01T09:00')
        ->set('ends_at', '2026-11-01T05:00')
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect($event->fresh()->title)->toBe($originalTitle);
});

test('organizer can delete their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('delete')
        ->assertRedirect(route('events.index'));

    expect(Event::count())->toBe(0);
});

test('other users cannot open the edit page of an event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('events.edit', $event))->assertForbidden();
});

test('other users cannot update or delete an event', function () {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertForbidden();

    expect($event->fresh()->title)->toBe($originalTitle);
});

test('guests are redirected to login from protected event pages', function () {
    $event = Event::factory()->create();

    $this->get(route('events.create'))->assertRedirect(route('login'));
    $this->get(route('events.edit', $event))->assertRedirect(route('login'));
});

test('guests can view the event index', function () {
    $this->get(route('events.index'))->assertOk();
});

test('edit button is shown only to the organizer', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user)
        ->get(route('events.index'))
        ->assertSee(route('events.edit', $event));

    $this->actingAs(User::factory()->create())
        ->get(route('events.index'))
        ->assertDontSee(route('events.edit', $event));
});
