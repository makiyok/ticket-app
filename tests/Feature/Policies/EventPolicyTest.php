<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->organizer()->create();
    $this->otherOrganizer = User::factory()->organizer()->create();
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
});

function actor(string $who): ?User
{
    return $who === 'guest' ? null : test()->{$who};
}

test('anyone can view a published event', function (string $who) {
    $event = Event::factory()->published()->for($this->owner, 'organizer')->create();

    expect(Gate::forUser(actor($who))->inspect('view', $event)->allowed())->toBeTrue();
})->with(['owner', 'otherOrganizer', 'admin', 'customer', 'guest']);

test('only the owner and admins can view non-public events', function (string $state, string $who, bool $allowed) {
    $event = Event::factory()->{$state}()->for($this->owner, 'organizer')->create();

    $response = Gate::forUser(actor($who))->inspect('view', $event);

    expect($response->allowed())->toBe($allowed);
    if (! $allowed) {
        expect($response->status())->toBe(404);
    }
})->with(['draft', 'unpublished'])->with([
    ['owner', true],
    ['admin', true],
    ['otherOrganizer', false],
    ['customer', false],
    ['guest', false],
]);

test('only organizers can create events', function (string $who, bool $allowed) {
    expect(Gate::forUser(actor($who))->allows('create', Event::class))->toBe($allowed);
})->with([
    ['owner', true],
    ['customer', false],
    ['admin', false],
]);

test('only the owner can update or delete an event', function (string $ability, string $who, bool $allowed) {
    $event = Event::factory()->for($this->owner, 'organizer')->create();

    expect(Gate::forUser(actor($who))->allows($ability, $event))->toBe($allowed);
})->with(['update', 'delete'])->with([
    ['owner', true],
    ['otherOrganizer', false],
    ['admin', false],
    ['customer', false],
]);

test('only admins can unpublish an event', function (string $who, bool $allowed) {
    $event = Event::factory()->published()->for($this->owner, 'organizer')->create();

    expect(Gate::forUser(actor($who))->allows('unpublish', $event))->toBe($allowed);
})->with([
    ['admin', true],
    ['owner', false],
    ['otherOrganizer', false],
    ['customer', false],
]);

test('only the owner can toggle publish, and never on an unpublished event', function (string $state, string $who, bool $allowed) {
    $event = Event::factory()->{$state}()->for($this->owner, 'organizer')->create();

    expect(Gate::forUser(actor($who))->allows('togglePublish', $event))->toBe($allowed);
})->with([
    ['draft', 'owner', true],
    ['published', 'owner', true],
    ['unpublished', 'owner', false],
    ['draft', 'otherOrganizer', false],
    ['published', 'admin', false],
    ['unpublished', 'admin', false],
    ['draft', 'customer', false],
]);
