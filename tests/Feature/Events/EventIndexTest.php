<?php

use App\Models\Event;
use App\Models\User;

test('guests see published upcoming events with links to their details', function () {
    $this->travelTo('2030-01-15 12:00:00');
    $event = Event::factory()->published()->create([
        'title' => '夏祭り',
        'venue' => '中央公園',
        'starts_at' => '2030-02-02 10:00:00',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('夏祭り')
        ->assertSee('中央公園')
        ->assertSee('2月2日(土) 10:00')
        ->assertSee(route('events.show', $event));
});

test('events that are not public or already started are not listed', function () {
    Event::factory()->draft()->create(['title' => '下書きのイベント']);
    Event::factory()->unpublished()->create(['title' => '非公開のイベント']);
    Event::factory()->published()->past()->create(['title' => '終わったイベント']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('下書きのイベント')
        ->assertDontSee('非公開のイベント')
        ->assertDontSee('終わったイベント')
        ->assertSee('現在、開催予定のイベントはありません。');
});

test('events are listed with the nearest date first', function () {
    Event::factory()->published()->create(['title' => '来月のイベント', 'starts_at' => now()->addMonth()]);
    Event::factory()->published()->create(['title' => '来週のイベント', 'starts_at' => now()->addWeek()]);

    $this->get(route('home'))->assertSeeInOrder(['来週のイベント', '来月のイベント']);
});

test('logged in customers can see the list', function () {
    Event::factory()->published()->create(['title' => '夏祭り']);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('夏祭り');
});
