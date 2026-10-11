<?php

use App\Models\Order;
use App\Models\User;

test('the buyer sees that the order was accepted', function () {
    $order = Order::factory()->create(['total_amount' => 6000]);

    $this->actingAs($order->user)
        ->get(route('orders.complete', $order))
        ->assertOk()
        ->assertSee('注文を受け付けました')
        ->assertSee('6,000円');
});

test('other customers cannot open the complete page', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.complete', $order))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.complete', $order))->assertRedirect(route('login'));
});
