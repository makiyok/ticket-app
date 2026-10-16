<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\User;

test('users see only their own orders', function () {
    $this->travelTo('2030-01-15 12:00:00');
    $user = User::factory()->create();
    $mine = Event::factory()->published()->create(['title' => '自分が買ったイベント', 'starts_at' => '2030-02-02 10:00:00']);
    Order::factory()->for($user)->for($mine)->paid()->create(['total_amount' => 6000]);
    Order::factory()->for(Event::factory()->published()->state(['title' => '他人が買ったイベント']))->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('自分が買ったイベント')
        ->assertSee('2月2日(土) 10:00')
        ->assertSee('1月15日(火) 12:00')
        ->assertSee('6,000円')
        ->assertSee('支払い済み')
        ->assertDontSee('他人が買ったイベント');
});

test('orders are listed newest first', function () {
    $user = User::factory()->create();
    Order::factory()->for($user)->for(Event::factory()->published()->state(['title' => '古い注文']))->create(['created_at' => now()->subDay()]);
    Order::factory()->for($user)->for(Event::factory()->published()->state(['title' => '新しい注文']))->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertSeeInOrder(['新しい注文', '古い注文']);
});

test('a message is shown when there are no orders', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('まだ注文がありません。');
});

test('guests are redirected to login', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});
