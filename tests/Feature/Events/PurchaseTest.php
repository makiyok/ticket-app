<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CheckoutService;
use Livewire\Livewire;
use Stripe\Exception\ApiConnectionException;

test('proceeding stores the selected quantities in the session and goes to the purchase page', function () {
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['capacity' => 50]);
    $student = TicketType::factory()->for($event)->create(['capacity' => 50]);

    Livewire::test('pages::events.show', ['event' => $event])
        ->set("quantities.{$general->id}", '2')
        ->call('proceed')
        ->assertRedirect(route('events.purchase', $event));

    expect(session("purchase.{$event->id}"))->toBe([$general->id => 2]);
});

test('proceeding without choosing a quantity shows an error', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create(['capacity' => 50]);

    Livewire::test('pages::events.show', ['event' => $event])
        ->call('proceed')
        ->assertHasErrors(['quantities' => '枚数を選んでください。'])
        ->assertNoRedirect();

    expect(session()->has("purchase.{$event->id}"))->toBeFalse();
});

test('guests are sent to login and come back to the purchase page', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->get(route('events.purchase', $event))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('events.purchase', $event));
});

test('the purchase page lists the selected ticket types with subtotals and the total', function () {
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['name' => '一般', 'price' => 3000, 'capacity' => 50]);
    $student = TicketType::factory()->for($event)->create(['name' => '学生', 'price' => 1500, 'capacity' => 50]);
    TicketType::factory()->for($event)->create(['name' => 'ペア', 'capacity' => 50]);

    $this->actingAs(User::factory()->create())
        ->withSession(['purchase' => [$event->id => [$general->id => 2, $student->id => 3]]])
        ->get(route('events.purchase', $event))
        ->assertOk()
        ->assertSeeInOrder(['一般', '3,000円', '2枚', '6,000円', '学生', '1,500円', '3枚', '4,500円', '合計', '10,500円'])
        ->assertDontSee('ペア');
});

test('the purchase page goes back to the event when nothing is selected', function () {
    $event = Event::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.purchase', $event))
        ->assertRedirect(route('events.show', $event));
});

test('checkout creates a pending order and redirects to the Stripe page', function () {
    $buyer = User::factory()->create();
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 3000, 'capacity' => 50]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 2]);
    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->once()
        ->with(Mockery::on(fn (Order $order): bool => $order->total_amount === 6000))
        ->andReturn('https://checkout.stripe.com/c/pay/cs_test_123');

    Livewire::actingAs($buyer)
        ->test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    $order = Order::sole();
    expect($order->user_id)->toBe($buyer->id)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_amount)->toBe(6000)
        ->and(session()->has("purchase.{$event->id}"))->toBeFalse();
});

test('a free order skips Stripe, is paid on the spot and goes to the complete page', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0, 'capacity' => 50]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 2]);
    $this->mock(CheckoutService::class)->shouldNotReceive('createSession');

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::events.purchase', ['event' => $event])
        ->call('checkout');

    $order = Order::sole();
    $component->assertRedirect(route('orders.complete', $order));
    expect($order->total_amount)->toBe(0)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and(Ticket::count())->toBe(2);
});

test('a Stripe failure cancels the order and shows an error', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 3000, 'capacity' => 50]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 1]);
    $this->mock(CheckoutService::class)
        ->shouldReceive('createSession')
        ->andThrow(new ApiConnectionException('network down'));

    Livewire::actingAs(User::factory()->create())
        ->test('pages::events.purchase', ['event' => $event])
        ->call('checkout')
        ->assertNoRedirect()
        ->assertSee('決済ページを開けませんでした');

    expect(Order::sole()->status)->toBe(OrderStatus::Cancelled)
        ->and($ticketType->remaining())->toBe(50);
});

test('checkout shows the purchase error and creates no order', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 5]);
    session()->put("purchase.{$event->id}", [$ticketType->id => 3]);
    $component = Livewire::actingAs(User::factory()->create())->test('pages::events.purchase', ['event' => $event]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 3]);

    $component->call('checkout')
        ->assertNoRedirect()
        ->assertSee('「一般」の残り枚数が足りません（残り2枚）。');

    expect(Order::count())->toBe(1)
        ->and(session("purchase.{$event->id}"))->toBe([$ticketType->id => 3]);
});
