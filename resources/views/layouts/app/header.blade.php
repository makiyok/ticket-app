@php
    // ヘッダー（PC）とメニュー（スマホ）で同じ項目を使う。未ログインでも表示できるよう、ユーザーは null を許す。
    $user = auth()->user();

    $navItems = [
        ['label' => 'イベント一覧', 'href' => route('home'), 'icon' => 'calendar-days', 'current' => request()->routeIs('home')],
    ];

    if ($user) {
        $navItems[] = ['label' => 'マイチケット', 'href' => url('/orders'), 'icon' => 'ticket', 'current' => request()->is('orders', 'orders/*')];

        if ($user->isOrganizer()) {
            $navItems[] = ['label' => '主催者メニュー', 'href' => route('organizer.events.index'), 'icon' => 'pencil-square', 'current' => request()->routeIs('organizer.*')];
        }

        if ($user->isAdmin()) {
            $navItems[] = ['label' => 'イベント管理', 'href' => url('/admin/events'), 'icon' => 'shield-check', 'current' => request()->is('admin/events', 'admin/events/*')];
            $navItems[] = ['label' => 'ユーザー管理', 'href' => url('/admin/users'), 'icon' => 'users', 'current' => request()->is('admin/users', 'admin/users/*')];
        }

        $navItems[] = ['label' => '設定', 'href' => route('profile.edit'), 'icon' => 'cog', 'current' => request()->is('settings', 'settings/*')];
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                @foreach ($navItems as $item)
                    <flux:navbar.item :icon="$item['icon']" :href="$item['href']" :current="$item['current']" wire:navigate>
                        {{ $item['label'] }}
                    </flux:navbar.item>
                @endforeach
            </flux:navbar>

            <flux:spacer />

            @auth
                <x-desktop-user-menu />
            @else
                <div class="flex items-center gap-2 max-lg:hidden">
                    <flux:button variant="ghost" :href="route('login')" wire:navigate>ログイン</flux:button>
                    <flux:button variant="primary" :href="route('register')" wire:navigate>会員登録</flux:button>
                </div>
            @endauth
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach ($navItems as $item)
                    <flux:sidebar.item :icon="$item['icon']" :href="$item['href']" :current="$item['current']" wire:navigate>
                        {{ $item['label'] }}
                    </flux:sidebar.item>
                @endforeach

                @guest
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>ログイン</flux:sidebar.item>
                    <flux:sidebar.item icon="user-plus" :href="route('register')" wire:navigate>会員登録</flux:sidebar.item>
                @endguest
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
