<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('organizer can add a ticket type to their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '一般')
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->call('addTicketType')
        ->assertHasNoErrors()
        ->assertSet('ticketName', '')
        ->assertSee('3,000円');

    $ticketType = $event->ticketTypes()->sole();

    expect($ticketType->name)->toBe('一般');
    expect($ticketType->price)->toBe(3000);
    expect($ticketType->capacity)->toBe(50);
});

test('edit page lists the event ticket types', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create(['name' => '学生', 'price' => 1500, 'capacity' => 20]);
    TicketType::factory()->create(['name' => '他のイベントの券種']);

    $this->actingAs($event->user);

    $this->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('学生')
        ->assertSee('1,500円')
        ->assertSee('20名')
        ->assertDontSee('他のイベントの券種');
});

test('edit page shows a message when there are no ticket types', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    $this->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('券種はまだありません。');
});

test('ticket type fields are required', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('addTicketType')
        ->assertHasErrors([
            'ticketName' => 'required',
            'ticketPrice' => 'required',
            'ticketCapacity' => 'required',
        ]);

    expect(TicketType::count())->toBe(0);
});

test('ticket type values must be in range', function (string $field, string $value) {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '一般')
        ->set('ticketPrice', '3000')
        ->set('ticketCapacity', '50')
        ->set($field, $value)
        ->call('addTicketType')
        ->assertHasErrors([$field => 'min']);

    expect(TicketType::count())->toBe(0);
})->with([
    'negative price' => ['ticketPrice', '-1'],
    'zero capacity' => ['ticketCapacity', '0'],
]);

test('free ticket type can be added', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketName', '招待')
        ->set('ticketPrice', '0')
        ->set('ticketCapacity', '1')
        ->call('addTicketType')
        ->assertHasNoErrors()
        ->assertSee('0円');
});

test('other users cannot add a ticket type', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertForbidden();

    expect(TicketType::count())->toBe(0);
});

test('organizer can delete a ticket type', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $ticketType->id)
        ->assertHasNoErrors();

    expect(TicketType::count())->toBe(0);
});

test('ticket type of another event cannot be deleted', function () {
    $event = Event::factory()->create();
    $otherTicketType = TicketType::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $otherTicketType->id);

    expect($otherTicketType->fresh())->not->toBeNull();
});

test('deleting an event deletes its ticket types', function () {
    $event = Event::factory()->has(TicketType::factory()->count(2))->create();

    $event->delete();

    expect(TicketType::count())->toBe(0);
});
