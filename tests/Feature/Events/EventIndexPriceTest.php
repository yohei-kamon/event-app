<?php

use App\Models\Event;
use App\Models\TicketType;

test('index shows the lowest ticket price', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create(['price' => 3000]);
    TicketType::factory()->for($event)->create(['price' => 1500]);

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('1,500円〜');
});

test('index shows 無料 when the lowest price is 0', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create(['price' => 0]);
    TicketType::factory()->for($event)->create(['price' => 2000]);

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('無料');
});

test('index shows a dash when the event has no ticket types', function () {
    Event::factory()->create();

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('─');
});
