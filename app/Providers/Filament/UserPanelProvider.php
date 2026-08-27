<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Register;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;

class UserPanelProvider extends AbstractPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel);

        return $panel
            ->default()
            ->path('/')
            ->registration(Register::class)
            ->viteTheme('resources/css/filament/user/theme.css')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->pages([
//                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\Filament\User\Widgets')
            ->widgets([]);
    }

    protected function panelId(): string
    {
        return 'user';
    }
}
