<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * 券種フォームの正しい入力値。引数で一部を上書きできる。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validTicketTypeInput(array $overrides = []): array
{
    return array_merge([
        'name' => '一般',
        'price' => 3000,
        'capacity' => 50,
        'sales_starts_at' => '2030-01-01T10:00',
        'sales_ends_at' => '2030-01-31T18:00',
    ], $overrides);
}

/**
 * 券種フォームに値を入れる。
 *
 * @param  array<string, mixed>  $input
 */
function fillTicketTypeForm(mixed $component, array $input): mixed
{
    foreach ($input as $key => $value) {
        $component->set("ticketTypeForm.{$key}", $value);
    }

    return $component;
}

beforeEach(function () {
    $this->owner = User::factory()->organizer()->create();
    $this->event = Event::factory()->for($this->owner, 'organizer')->create();
});

test('the owner can add a ticket type', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event]);

    fillTicketTypeForm($component, validTicketTypeInput());

    $component->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSet('ticketTypeForm.name', '')
        ->assertSet('ticketTypeForm.price', '')
        ->assertSet('ticketTypeForm.capacity', '')
        ->assertSet('ticketTypeForm.sales_starts_at', '')
        ->assertSet('ticketTypeForm.sales_ends_at', '')
        ->assertSet('editingTicketTypeId', null);

    $ticketType = TicketType::sole();
    expect($ticketType->event_id)->toBe($this->event->id)
        ->and($ticketType->name)->toBe('一般')
        ->and($ticketType->price)->toBe(3000)
        ->and($ticketType->capacity)->toBe(50)
        ->and($ticketType->sales_starts_at->format('Y-m-d\TH:i'))->toBe('2030-01-01T10:00')
        ->and($ticketType->sales_ends_at->format('Y-m-d\TH:i'))->toBe('2030-01-31T18:00');
});

test('price and capacity given as strings are saved as integers', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event]);

    fillTicketTypeForm($component, validTicketTypeInput(['price' => '3000', 'capacity' => '50']));

    $component->call('saveTicketType')->assertHasNoErrors();

    $ticketType = TicketType::sole();
    expect($ticketType->price)->toBe(3000)
        ->and($ticketType->capacity)->toBe(50)
        ->and($ticketType->getRawOriginal('price'))->toBeInt()
        ->and($ticketType->getRawOriginal('capacity'))->toBeInt();
});

test('the owner can edit a ticket type without creating a new one', function () {
    $ticketType = TicketType::factory()->for($this->event)->create([
        'name' => '一般',
        'price' => 3000,
        'capacity' => 50,
        'sales_starts_at' => '2030-01-01 10:00:00',
        'sales_ends_at' => '2030-01-31 18:00:00',
    ]);

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $ticketType->id)
        ->assertSet('editingTicketTypeId', $ticketType->id)
        ->assertSet('ticketTypeForm.name', '一般')
        ->assertSet('ticketTypeForm.price', 3000)
        ->assertSet('ticketTypeForm.capacity', 50)
        ->assertSet('ticketTypeForm.sales_starts_at', '2030-01-01T10:00')
        ->assertSet('ticketTypeForm.sales_ends_at', '2030-01-31T18:00');

    $component->set('ticketTypeForm.name', '学生')
        ->set('ticketTypeForm.price', '1500')
        ->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSet('editingTicketTypeId', null)
        ->assertSet('ticketTypeForm.name', '');

    expect(TicketType::count())->toBe(1);

    $fresh = $ticketType->fresh();
    expect($fresh->name)->toBe('学生')
        ->and($fresh->price)->toBe(1500)
        ->and($fresh->capacity)->toBe(50)
        ->and($fresh->event_id)->toBe($this->event->id);
});

test('cancelling an edit clears the form and keeps the data', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般']);

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $ticketType->id)
        ->set('ticketTypeForm.name', '書き換え途中')
        ->call('cancelTicketTypeEdit')
        ->assertSet('editingTicketTypeId', null)
        ->assertSet('ticketTypeForm.name', '')
        ->assertSet('ticketTypeForm.price', '')
        ->assertSet('ticketTypeForm.capacity', '');

    expect($ticketType->fresh()->name)->toBe('一般')
        ->and(TicketType::count())->toBe(1);
});

test('the owner can delete the last ticket type of a draft event', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('deleteTicketType', $ticketType->id)
        ->assertHasNoErrors();

    expect(TicketType::count())->toBe(0);
});

test('deleting the ticket type being edited resets the form', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $ticketType->id)
        ->assertSet('editingTicketTypeId', $ticketType->id)
        ->call('deleteTicketType', $ticketType->id)
        ->assertSet('editingTicketTypeId', null)
        ->assertSet('ticketTypeForm.name', '')
        ->assertSet('ticketTypeForm.price', '');

    expect(TicketType::count())->toBe(0);
});

test('invalid ticket type input is rejected on create', function (array $overrides, array $errors) {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event]);

    fillTicketTypeForm($component, validTicketTypeInput($overrides));

    $component->call('saveTicketType')->assertHasErrors($errors);

    expect(TicketType::count())->toBe(0);
})->with([
    'name too long' => [['name' => str_repeat('あ', 51)], ['ticketTypeForm.name' => 'max']],
    'name empty' => [['name' => ''], ['ticketTypeForm.name' => 'required']],
    'negative price' => [['price' => -1], ['ticketTypeForm.price' => 'min']],
    'price too large' => [['price' => 10000000], ['ticketTypeForm.price' => 'max']],
    'decimal price' => [['price' => '1.5'], ['ticketTypeForm.price' => 'integer']],
    'text price' => [['price' => 'abc'], ['ticketTypeForm.price' => 'integer']],
    'empty price' => [['price' => ''], ['ticketTypeForm.price' => 'required']],
    'zero capacity' => [['capacity' => 0], ['ticketTypeForm.capacity' => 'min']],
    'capacity too large' => [['capacity' => 100001], ['ticketTypeForm.capacity' => 'max']],
    'decimal capacity' => [['capacity' => '1.5'], ['ticketTypeForm.capacity' => 'integer']],
    'text capacity' => [['capacity' => 'abc'], ['ticketTypeForm.capacity' => 'integer']],
    'empty capacity' => [['capacity' => ''], ['ticketTypeForm.capacity' => 'required']],
    'empty start' => [['sales_starts_at' => ''], ['ticketTypeForm.sales_starts_at' => 'required']],
    'bad date format' => [['sales_starts_at' => '2030/01/01'], ['ticketTypeForm.sales_starts_at' => 'date_format']],
    'end before start' => [['sales_ends_at' => '2029-12-31T10:00'], ['ticketTypeForm.sales_ends_at' => 'after']],
    'end equals start' => [['sales_ends_at' => '2030-01-01T10:00'], ['ticketTypeForm.sales_ends_at' => 'after']],
]);

test('boundary values are accepted', function (array $overrides) {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event]);

    fillTicketTypeForm($component, validTicketTypeInput($overrides));

    $component->call('saveTicketType')->assertHasNoErrors();

    expect(TicketType::count())->toBe(1);
})->with([
    'name of 50 characters' => [['name' => str_repeat('あ', 50)]],
    'free price' => [['price' => 0]],
    'free price as string' => [['price' => '0']],
    'capacity of 1' => [['capacity' => 1]],
    'max price and capacity' => [['price' => 9999999, 'capacity' => 100000]],
]);

test('invalid input is rejected on update and the data is unchanged', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般', 'price' => 3000]);

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $ticketType->id)
        ->set('ticketTypeForm.name', '')
        ->set('ticketTypeForm.price', '')
        ->call('saveTicketType')
        ->assertHasErrors(['ticketTypeForm.name' => 'required', 'ticketTypeForm.price' => 'required'])
        ->assertSet('editingTicketTypeId', $ticketType->id);

    $fresh = $ticketType->fresh();
    expect($fresh->name)->toBe('一般')
        ->and($fresh->price)->toBe(3000);
});

test('other organizers cannot open the edit page to manage ticket types', function () {
    $other = User::factory()->organizer()->create();

    Livewire::actingAs($other)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertForbidden();
});

test('ticket type methods re-check authorization after ownership changes', function (string $method) {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般', 'price' => 3000]);
    $other = User::factory()->organizer()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event]);

    fillTicketTypeForm($component, validTicketTypeInput(['name' => '乗っ取り券']));

    $this->event->forceFill(['organizer_id' => $other->id])->save();

    $takesId = in_array($method, ['editTicketType', 'deleteTicketType'], true);

    $component->call($method, ...($takesId ? [$ticketType->id] : []))
        ->assertForbidden();

    $fresh = $ticketType->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('一般')
        ->and($fresh->price)->toBe(3000)
        ->and(TicketType::count())->toBe(1);
})->with(['saveTicketType', 'editTicketType', 'deleteTicketType', 'cancelTicketTypeEdit']);

test('saving an edit is forbidden after ownership changes', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般']);
    $other = User::factory()->organizer()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $ticketType->id)
        ->set('ticketTypeForm.name', '乗っ取り券');

    $this->event->forceFill(['organizer_id' => $other->id])->save();

    $component->call('saveTicketType')->assertForbidden();

    expect($ticketType->fresh()->name)->toBe('一般');
});

test('ticket types of other events cannot be edited or deleted', function (string $method) {
    $otherEvent = Event::factory()->create();
    $foreign = TicketType::factory()->for($otherEvent)->create(['name' => '他人の券種']);

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call($method, $foreign->id)
        ->assertNotFound();

    $fresh = $foreign->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('他人の券種')
        ->and($fresh->event_id)->toBe($otherEvent->id);
})->with(['editTicketType', 'deleteTicketType']);

test('a draft event with no ticket types cannot be published', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertSee('公開するには券種が1つ以上必要です。')
        ->call('togglePublish')
        ->assertHasErrors('publish');

    expect($this->event->fresh()->status)->toBe(EventStatus::Draft);
});

test('a draft event with a ticket type can be published', function () {
    TicketType::factory()->for($this->event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertDontSee('公開するには券種が1つ以上必要です。')
        ->call('togglePublish')
        ->assertHasNoErrors();

    expect($this->event->fresh()->status)->toBe(EventStatus::Published);
});

test('the last ticket type cannot be deleted unless the event is a draft', function (string $state, string $message) {
    $event = Event::factory()->for($this->owner, 'organizer')->{$state}()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $ticketType->id)
        ->assertHasErrors('ticketTypes')
        ->assertSee($message);

    expect($ticketType->fresh())->not->toBeNull()
        ->and($event->fresh()->status)->toBe(EventStatus::from($state));
})->with([
    'published' => ['published', '公開中のイベントの最後の券種は削除できません。'],
    'unpublished' => ['unpublished', '非公開のイベントの最後の券種は削除できません。'],
]);

test('the ticket type being edited cannot be switched from the browser', function () {
    $editing = TicketType::factory()->for($this->event)->create();
    $another = TicketType::factory()->for($this->event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('editTicketType', $editing->id)
        ->set('editingTicketTypeId', $another->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('a ticket type of a published event can be deleted when another remains', function () {
    $event = Event::factory()->for($this->owner, 'organizer')->published()->create();
    $first = TicketType::factory()->for($event)->create();
    $second = TicketType::factory()->for($event)->create();

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $first->id)
        ->assertHasNoErrors();

    expect($first->fresh())->toBeNull()
        ->and($second->fresh())->not->toBeNull();
});

test('ticket types are listed with formatted price and capacity', function () {
    TicketType::factory()->for($this->event)->create([
        'name' => '特別観覧席',
        'price' => 3000,
        'capacity' => 50,
    ]);

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertSee('特別観覧席')
        ->assertSee('3,000円')
        ->assertSee('50枚');
});
