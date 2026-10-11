<?php

use App\Models\User;
use Illuminate\Testing\TestView;

function renderAppLayout(): TestView
{
    return test()->blade('<x-layouts::app>本文</x-layouts::app>');
}

test('guests can see the layout with login and register links', function () {
    renderAppLayout()
        ->assertSee('本文')
        ->assertSee('イベント一覧')
        ->assertSee('ログイン')
        ->assertSee('会員登録')
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertDontSee('マイチケット')
        ->assertDontSee('設定')
        ->assertDontSee('主催者メニュー')
        ->assertDontSee('イベント管理')
        ->assertDontSee('ユーザー管理');
});

test('customers see my tickets and settings but no organizer or admin links', function () {
    $this->actingAs(User::factory()->create());

    renderAppLayout()
        ->assertSee('イベント一覧')
        ->assertSee('マイチケット')
        ->assertSee(url('/orders'))
        ->assertSee('設定')
        ->assertDontSee('会員登録')
        ->assertDontSee('主催者メニュー')
        ->assertDontSee('イベント管理')
        ->assertDontSee('ユーザー管理');
});

test('organizers also see the organizer menu', function () {
    $this->actingAs(User::factory()->organizer()->create());

    renderAppLayout()
        ->assertSee('マイチケット')
        ->assertSee('主催者メニュー')
        ->assertSee(route('organizer.events.index'))
        ->assertDontSee('イベント管理')
        ->assertDontSee('ユーザー管理');
});

test('admins also see event and user management', function () {
    $this->actingAs(User::factory()->admin()->create());

    renderAppLayout()
        ->assertSee('マイチケット')
        ->assertSee('イベント管理')
        ->assertSee(url('/admin/events'))
        ->assertSee('ユーザー管理')
        ->assertSee(url('/admin/users'))
        ->assertDontSee('主催者メニュー');
});

test('a logged-in page still renders inside the header layout', function () {
    $this->actingAs(User::factory()->organizer()->create())
        ->get(route('organizer.events.index'))
        ->assertOk()
        ->assertSee('主催者メニュー')
        ->assertSee('ログアウト');
});
