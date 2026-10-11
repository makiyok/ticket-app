<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;

test('guests can see a published event with its ticket types', function () {
    $this->travelTo('2030-01-15 12:00:00');
    $event = Event::factory()->published()->create([
        'title' => '夏祭り',
        'description' => '屋台がたくさん出ます',
        'venue' => '中央公園',
        'starts_at' => '2030-02-02 10:00:00',
        'ends_at' => '2030-02-02 16:30:00',
    ]);
    $ticketType = TicketType::factory()->for($event)->create([
        'name' => '一般',
        'price' => 3000,
        'capacity' => 50,
        'sales_starts_at' => '2030-01-10 09:00:00',
        'sales_ends_at' => '2030-02-01 18:00:00',
    ]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 8]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('夏祭り')
        ->assertSee('屋台がたくさん出ます')
        ->assertSee('中央公園')
        ->assertSee('2月2日(土) 10:00')
        ->assertSee('2月2日(土) 16:30')
        ->assertSee('一般')
        ->assertSee('3,000円')
        ->assertSee('42枚')
        ->assertSee('1月10日(木) 09:00')
        ->assertSee('2月1日(金) 18:00')
        ->assertSee('販売中');
});

test('each ticket type shows its sales state', function (string $state, int $sold, string $label) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->{$state}()->create(['capacity' => 5]);
    if ($sold > 0) {
        OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => $sold]);
    }

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($label)
        ->assertDontSee('販売中');
})->with([
    'before the sales period' => ['beforeSale', 0, '販売前'],
    'after the sales period' => ['afterSale', 0, '販売終了'],
    'sold out' => ['configure', 5, '売り切れ'],
]);

test('non-public events are hidden from other people', function (string $state, string $who) {
    $event = Event::factory()->{$state}()->create();
    if ($who !== 'guest') {
        $this->actingAs(User::factory()->{$who}()->create());
    }

    $this->get(route('events.show', $event))->assertNotFound();
})->with(['draft', 'unpublished'])->with([
    'guest' => ['guest'],
    'customer' => ['configure'],
    'other organizer' => ['organizer'],
]);

test('the organizer and admins can preview a draft event', function (string $who) {
    $organizer = User::factory()->organizer()->create();
    $event = Event::factory()->draft()->for($organizer, 'organizer')->create(['title' => '準備中のイベント']);
    $viewer = $who === 'organizer' ? $organizer : User::factory()->admin()->create();

    $this->actingAs($viewer)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('準備中のイベント')
        ->assertSee('下書き');
})->with(['organizer', 'admin']);

test('the quantity select offers up to the smaller of 10 and the remaining count', function (int $capacity, int $max) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => $capacity]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee("quantity-{$ticketType->id}")
        ->assertSee("{$max}枚</option>", false)
        ->assertDontSee(($max + 1).'枚</option>', false)
        ->assertSee('購入手続きへ進む');
})->with([
    'fewer than 10 remaining' => [3, 3],
    'more than 10 remaining' => [50, 10],
]);

test('ticket types that cannot be bought have no quantity select', function (string $state, int $sold) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->{$state}()->create(['capacity' => 5]);
    if ($sold > 0) {
        OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => $sold]);
    }

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSee("quantity-{$ticketType->id}");
})->with([
    'before the sales period' => ['beforeSale', 0],
    'after the sales period' => ['afterSale', 0],
    'sold out' => ['configure', 5],
]);
