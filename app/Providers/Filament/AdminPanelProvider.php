<?php

namespace App\Providers\Filament;

use App\Enums\Idioma;
use App\Http\Middleware\EstablecerIdioma;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->brandName('Intranet DEMA')
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->userMenuItems(collect(Idioma::cases())->map(fn (Idioma $idioma) => Action::make('idioma_'.$idioma->value)
                ->label($idioma->nombre())
                ->icon(fn () => app()->getLocale() === $idioma->value ? 'heroicon-m-check' : 'heroicon-m-language')
                ->url(fn () => route('idioma', $idioma->value))
            )->all())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => Blade::render(<<<'BLADE'
                <div style="display:flex; justify-content:center; gap:1rem; font-size:.875rem;">
                    @foreach (\App\Enums\Idioma::cases() as $idioma)
                        <a href="{{ route('idioma', $idioma->value) }}" @style(['font-weight:700; text-decoration:underline' => app()->getLocale() === $idioma->value])>{{ $idioma->nombre() }}</a>
                    @endforeach
                </div>
                BLADE))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent: also applies to Livewire requests (actions, filters, modals...).
            ->middleware([EstablecerIdioma::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
