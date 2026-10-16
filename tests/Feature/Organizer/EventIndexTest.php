<?php

use App\Models\Event;
use App\Models\User;

test('organizers see only their own events', function () {
    $organizer = User::factory()->organizer()->create();
    $mine = Event::factory()->for($organizer, 'organizer')->create(['title' => '自分のイベント']);
    $theirs = Event::factory()->create(['title' => '他人のイベント']);

    $this->actingAs($organizer)
        ->get(route('organizer.events.index'))
        ->assertOk()
        ->assertSee('自分のイベント')
        ->assertDontSee('他人のイベント');
});

test('status labels are shown', function () {
    $organizer = User::factory()->organizer()->create();
    Event::factory()->for($organizer, 'organizer')->draft()->create();
    Event::factory()->for($organizer, 'organizer')->published()->create();
    Event::factory()->for($organizer, 'organizer')->unpublished()->create();

    $this->actingAs($organizer)
        ->get(route('organizer.events.index'))
        ->assertSee('下書き')
        ->assertSee('公開中')
        ->assertSee('非公開');
});

test('customers are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('organizer.events.index'))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $this->get(route('organizer.events.index'))->assertRedirect(route('login'));
});
