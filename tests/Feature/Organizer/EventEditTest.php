<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->organizer()->create();
    $this->event = Event::factory()->for($this->owner, 'organizer')->create();
});

test('the owner can update the event', function () {
    $startsAt = now()->addDays(5)->format('Y-m-d\TH:i');
    $endsAt = now()->addDays(6)->format('Y-m-d\TH:i');

    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertSet('form.title', $this->event->title)
        ->set('form.title', '更新後のタイトル')
        ->set('form.starts_at', $startsAt)
        ->set('form.ends_at', $endsAt)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $this->event->fresh();
    expect($fresh->title)->toBe('更新後のタイトル')
        ->and($fresh->starts_at->format('Y-m-d\TH:i'))->toBe($startsAt)
        ->and($fresh->organizer_id)->toBe($this->owner->id);
});

test('the owner can toggle between draft and published', function () {
    TicketType::factory()->for($this->event)->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertSee('公開する');

    $component->call('togglePublish');
    expect($this->event->fresh()->status)->toBe(EventStatus::Published);

    $component->assertSee('下書きに戻す')->call('togglePublish');
    expect($this->event->fresh()->status)->toBe(EventStatus::Draft);
});

test('the owner can delete the event', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->call('deleteEvent')
        ->assertRedirect(route('organizer.events.index'));

    expect(Event::count())->toBe(0);
});

test('other organizers cannot open the edit page', function () {
    $other = User::factory()->organizer()->create();

    $this->actingAs($other)
        ->get(route('organizer.events.edit', $this->event))
        ->assertForbidden();

    Livewire::actingAs($other)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->assertForbidden();
});

test('validation errors are shown on update', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->set('form.title', '')
        ->set('form.venue', str_repeat('あ', 101))
        ->set('form.ends_at', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors(['form.title' => 'required', 'form.venue' => 'max', 'form.ends_at']);

    expect($this->event->fresh()->title)->toBe($this->event->title);
});

test('methods re-check authorization after ownership changes', function (string $method) {
    $other = User::factory()->organizer()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $this->event])
        ->set('form.title', '乗っ取りタイトル');

    $this->event->forceFill(['organizer_id' => $other->id])->save();

    $component->call($method)->assertForbidden();

    $fresh = $this->event->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->title)->toBe($this->event->title)
        ->and($fresh->status)->toBe(EventStatus::Draft)
        ->and($fresh->organizer_id)->toBe($other->id);
})->with(['save', 'togglePublish', 'deleteEvent']);

test('an unpublished event cannot be toggled by the owner', function () {
    $event = Event::factory()->for($this->owner, 'organizer')->unpublished()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::organizer.events.edit', ['event' => $event])
        ->assertDontSee('公開する')
        ->assertDontSee('下書きに戻す')
        ->assertDontSeeHtml('data-test="toggle-publish-button"');

    $component->call('togglePublish')->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Unpublished);
});
