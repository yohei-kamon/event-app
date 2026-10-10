<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

/**
 * 登録画面に有効な入力を入れた状態のコンポーネントを返す。
 */
function validCreateForm(array $overrides = [])
{
    $values = array_merge([
        'title' => '朝焼けトレッキング',
        'description' => '早朝の山を歩きます。',
        'venue' => '岩手山 馬返し登山口',
        'starts_at' => '2026-11-01T05:00',
        'ends_at' => '2026-11-01T09:00',
    ], $overrides);

    $component = Livewire::test('pages::events.create');

    foreach ($values as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

/**
 * 編集画面に有効な入力を入れた状態のコンポーネントを返す。
 */
function validEditForm(Event $event, array $overrides = [])
{
    $values = array_merge([
        'title' => '更新後のタイトル',
        'description' => '更新後の説明',
        'venue' => '更新後の会場',
        'starts_at' => '2026-11-01T05:00',
        'ends_at' => '2026-11-01T09:00',
    ], $overrides);

    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    foreach ($values as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

// ---- イベント登録: 不正な入力 ----

test('event text fields reject values over the length limit on create', function (string $field, int $length) {
    $this->actingAs(User::factory()->create());

    validCreateForm([$field => str_repeat('あ', $length)])
        ->call('save')
        ->assertHasErrors([$field => 'max']);

    expect(Event::count())->toBe(0);
})->with([
    'title 101 chars' => ['title', 101],
    'venue 101 chars' => ['venue', 101],
    'description 2001 chars' => ['description', 2001],
]);

test('event text fields accept values exactly at the length limit on create', function (string $field, int $length) {
    $this->actingAs(User::factory()->create());

    validCreateForm([$field => str_repeat('あ', $length)])
        ->call('save')
        ->assertHasNoErrors();

    expect(Event::sole()->{$field})->toHaveLength($length);
})->with([
    'title 100 chars' => ['title', 100],
    'venue 100 chars' => ['venue', 100],
    'description 2000 chars' => ['description', 2000],
]);

test('event dates must be valid dates on create', function (string $field) {
    $this->actingAs(User::factory()->create());

    validCreateForm([$field => 'not-a-date'])
        ->call('save')
        ->assertHasErrors([$field]);

    expect(Event::count())->toBe(0);
})->with([
    'starts_at' => ['starts_at'],
    'ends_at' => ['ends_at'],
]);

test('event end time equal to start time is rejected on create', function () {
    $this->actingAs(User::factory()->create());

    validCreateForm(['starts_at' => '2026-11-01T05:00', 'ends_at' => '2026-11-01T05:00'])
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);

    expect(Event::count())->toBe(0);
});

// ---- イベント編集: 不正な入力 ----

test('event fields are required on update', function () {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('title', '')
        ->set('description', '')
        ->set('venue', '')
        ->set('starts_at', '')
        ->set('ends_at', '')
        ->call('save')
        ->assertHasErrors([
            'title' => 'required',
            'description' => 'required',
            'venue' => 'required',
            'starts_at' => 'required',
            'ends_at' => 'required',
        ]);

    expect($event->fresh()->title)->toBe($originalTitle);
});

test('event text fields reject values over the length limit on update', function (string $field, int $length) {
    $event = Event::factory()->create();
    $original = $event->{$field};

    $this->actingAs($event->user);

    validEditForm($event, [$field => str_repeat('あ', $length)])
        ->call('save')
        ->assertHasErrors([$field => 'max']);

    expect($event->fresh()->{$field})->toBe($original);
})->with([
    'title 101 chars' => ['title', 101],
    'venue 101 chars' => ['venue', 101],
    'description 2001 chars' => ['description', 2001],
]);

test('event title accepts exactly 100 chars on update', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    validEditForm($event, ['title' => str_repeat('あ', 100)])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->title)->toHaveLength(100);
});

test('event dates must be valid dates on update', function (string $field) {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs($event->user);

    validEditForm($event, [$field => 'not-a-date'])
        ->call('save')
        ->assertHasErrors([$field]);

    expect($event->fresh()->title)->toBe($originalTitle);
})->with([
    'starts_at' => ['starts_at'],
    'ends_at' => ['ends_at'],
]);

// ---- イベント: 権限 ----

test('guests cannot open the edit component', function () {
    $event = Event::factory()->create();

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertForbidden();
});

test('other users cannot update an event through an already mounted component', function () {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component->set('title', '乗っ取り')->call('save')->assertForbidden();

    expect($event->fresh()->title)->toBe($originalTitle);
});

test('other users cannot delete an event through an already mounted component', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component->call('delete')->assertForbidden();

    expect(Event::count())->toBe(1);
});

// ---- 券種: 不正な入力 ----

test('ticket type name rejects values over 50 chars', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', str_repeat('あ', 51))
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->call('addTicketType')
        ->assertHasErrors(['ticketName' => 'max']);

    expect(TicketType::count())->toBe(0);
});

test('ticket type name accepts exactly 50 chars', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', str_repeat('あ', 50))
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->call('addTicketType')
        ->assertHasNoErrors();

    expect($event->ticketTypes()->sole()->name)->toHaveLength(50);
});

test('ticket type price and capacity reject out of range, decimal and non numeric values', function (string $field, string $value, string $rule) {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '一般')
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->set($field, $value)
        ->call('addTicketType')
        ->assertHasErrors([$field => $rule]);

    expect(TicketType::count())->toBe(0);
})->with([
    'price over max' => ['ticketPrice', '1000001', 'max'],
    'price decimal' => ['ticketPrice', '1500.5', 'integer'],
    'price string' => ['ticketPrice', 'abc', 'integer'],
    'capacity over max' => ['ticketCapacity', '100001', 'max'],
    'capacity decimal' => ['ticketCapacity', '2.5', 'integer'],
    'capacity string' => ['ticketCapacity', 'abc', 'integer'],
]);

test('ticket type price and capacity accept the upper limits', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '最大')
        ->set('ticketPrice', '1000000')
        ->set('ticketCapacity', '100000')
        ->call('addTicketType')
        ->assertHasNoErrors();

    $ticketType = $event->ticketTypes()->sole();

    expect($ticketType->price)->toBe(1000000);
    expect($ticketType->capacity)->toBe(100000);
});

// ---- 券種: 権限 ----

test('other users cannot add a ticket type through an already mounted component', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->set('ticketName', '一般')
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->call('addTicketType')
        ->assertForbidden();

    expect(TicketType::count())->toBe(0);
});

test('other users cannot delete a ticket type through an already mounted component', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component->call('deleteTicketType', $ticketType->id)->assertForbidden();

    expect($ticketType->fresh())->not->toBeNull();
});

test('deleting a ticket type of another event responds with 404 and keeps it', function () {
    $event = Event::factory()->create();
    $otherTicketType = TicketType::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $otherTicketType->id)
        ->assertNotFound();

    expect($otherTicketType->fresh())->not->toBeNull();
});

// ---- モデル ----

test('isOwnedBy is true only for the owner', function () {
    $event = Event::factory()->create();

    expect($event->isOwnedBy($event->user))->toBeTrue();
    expect($event->isOwnedBy(User::factory()->create()))->toBeFalse();
    expect($event->isOwnedBy(null))->toBeFalse();
});

test('ticket type formats price with thousands separator and yen', function () {
    $ticketType = TicketType::factory()->make(['price' => 1234567]);

    expect($ticketType->formatted_price)->toBe('1,234,567円');
});
