<?php

namespace App\Providers\Filament;

use App\Filament\Support\Color;
use App\Filament\User\Widgets\HistoryChart;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Widgets\FilamentInfoWidget;

class AdminPanelProvider extends AbstractPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel);

        return $panel
            ->path('admin')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
//            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
//            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // ->pages([
            //     Dashboard::class,
            // ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                FilamentInfoWidget::class,
                HistoryChart::class,
            ]);
    }

    protected function panelId(): string
    {
        return 'admin';
    }
}
