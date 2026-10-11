<?php

use App\Enums\EventStatus;
use App\Models\Event;

test('published scope returns only published events', function () {
    $published = Event::factory()->published()->create();
    Event::factory()->draft()->create();
    Event::factory()->unpublished()->create();

    expect(Event::published()->pluck('id')->all())->toBe([$published->id]);
});

test('upcoming scope excludes past events', function () {
    $upcoming = Event::factory()->create();
    Event::factory()->past()->create();

    expect(Event::upcoming()->pluck('id')->all())->toBe([$upcoming->id]);
});

test('isPublished reflects the status', function () {
    expect(Event::factory()->published()->make()->isPublished())->toBeTrue()
        ->and(Event::factory()->draft()->make()->isPublished())->toBeFalse()
        ->and(Event::factory()->unpublished()->make()->isPublished())->toBeFalse();
});

test('event status has labels and colors', function () {
    expect(EventStatus::Draft->label())->toBe('下書き')
        ->and(EventStatus::Published->label())->toBe('公開中')
        ->and(EventStatus::Unpublished->label())->toBe('非公開')
        ->and(EventStatus::Draft->color())->toBe('zinc')
        ->and(EventStatus::Published->color())->toBe('green')
        ->and(EventStatus::Unpublished->color())->toBe('red');
});
