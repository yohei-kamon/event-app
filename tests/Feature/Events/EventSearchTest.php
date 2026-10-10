<?php

use App\Models\Event;
use Livewire\Livewire;

test('search narrows the list by title', function () {
    Event::factory()->create(['title' => '朝焼けトレッキング']);
    Event::factory()->create(['title' => '雪上ヨガ体験']);

    Livewire::test('pages::events.index')
        ->set('search', 'トレッキング')
        ->assertSee('朝焼けトレッキング')
        ->assertDontSee('雪上ヨガ体験');
});

test('search narrows the list by venue', function () {
    Event::factory()->create(['title' => 'イベントA', 'venue' => '八幡平市民ホール']);
    Event::factory()->create(['title' => 'イベントB', 'venue' => '盛岡公民館']);

    Livewire::test('pages::events.index')
        ->set('search', '公民館')
        ->assertSee('イベントB')
        ->assertDontSee('イベントA');
});

test('search treats percent as a literal character', function () {
    Event::factory()->create(['title' => '100%天然温泉']);
    Event::factory()->create(['title' => '雪上ヨガ体験']);

    Livewire::test('pages::events.index')
        ->set('search', '%')
        ->assertSee('100%天然温泉')
        ->assertDontSee('雪上ヨガ体験');
});

test('search shows a message when nothing matches', function () {
    Event::factory()->create(['title' => '雪上ヨガ体験']);

    Livewire::test('pages::events.index')
        ->set('search', '存在しない')
        ->assertSee('条件に合うイベントはありません。');
});
