<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

function validEventInput(array $overrides = []): array
{
    return array_merge([
        'title' => 'テストイベント',
        'description' => '説明文です。',
        'venue' => 'テスト会場',
        'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ], $overrides);
}

function fillEventForm($component, array $input)
{
    foreach ($input as $key => $value) {
        $component->set("form.$key", $value);
    }

    return $component;
}

test('an organizer can create an event as a draft', function () {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput())->call('save')->assertHasNoErrors();

    $event = Event::sole();
    expect($event->title)->toBe('テストイベント')
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->organizer_id)->toBe($organizer->id);

    $component->assertRedirect(route('organizer.events.edit', $event));
});

test('all fields are required', function (string $field) {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput([$field => '']))
        ->call('save')
        ->assertHasErrors(["form.$field" => 'required']);

    expect(Event::count())->toBe(0);
})->with(['title', 'description', 'venue', 'starts_at', 'ends_at']);

test('title and venue are limited to 100 characters', function (string $field) {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput([$field => str_repeat('あ', 101)]))
        ->call('save')
        ->assertHasErrors(["form.$field" => 'max']);
    expect(Event::count())->toBe(0);

    $component->set("form.$field", str_repeat('あ', 100))
        ->call('save')
        ->assertHasNoErrors();
    expect(Event::count())->toBe(1);
})->with(['title', 'venue']);

test('ends_at must be after starts_at', function (string $endsAt) {
    $organizer = User::factory()->organizer()->create();
    $startsAt = now()->addDays(2)->format('Y-m-d\TH:i');

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput(['starts_at' => $startsAt, 'ends_at' => $endsAt]))
        ->call('save')
        ->assertHasErrors(['form.ends_at']);

    expect(Event::count())->toBe(0);
})->with([
    'before' => fn () => now()->addDay()->format('Y-m-d\TH:i'),
    'same time' => fn () => now()->addDays(2)->format('Y-m-d\TH:i'),
]);

test('customers cannot open the create page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('organizer.events.create'))
        ->assertForbidden();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::organizer.events.create')
        ->assertForbidden();
});

test('save re-checks authorization after the page was opened', function () {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput());

    $organizer->forceFill(['role' => 'customer'])->save();

    $component->call('save')->assertForbidden();

    expect(Event::count())->toBe(0);
});

test('dates must use the datetime-local format', function (string $field, string $value) {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput([$field => $value]))
        ->call('save')
        ->assertHasErrors(["form.$field" => 'date_format']);

    expect(Event::count())->toBe(0);
})->with(['starts_at', 'ends_at'])->with([
    'relative word' => 'tomorrow',
    'with timezone offset' => '2099-01-01T10:00+00:00',
    'date only' => '2099-01-01',
]);

test('description is limited to 5000 characters', function () {
    $organizer = User::factory()->organizer()->create();

    $component = Livewire::actingAs($organizer)->test('pages::organizer.events.create');
    fillEventForm($component, validEventInput(['description' => str_repeat('あ', 5001)]))
        ->call('save')
        ->assertHasErrors(['form.description' => 'max']);
    expect(Event::count())->toBe(0);

    $component->set('form.description', str_repeat('あ', 5000))
        ->call('save')
        ->assertHasNoErrors();
    expect(Event::count())->toBe(1);
});
