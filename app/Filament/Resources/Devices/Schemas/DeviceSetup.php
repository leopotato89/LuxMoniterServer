<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Models\Device;
use App\Support\InverterSettings;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DeviceSetup
{

    /**
     * Schema cài đặt biến tần cho action modal.
     *
     * - Công tắc (switch): lưu NGAY khi đổi trạng thái (live + afterStateUpdated).
     * - Input/select/time: có nút Lưu nhỏ (suffixAction) — bấm là ghi field đó.
     * - Field phụ thuộc (showIf/show/showAc/showGen): ẩn/hiện động như ESP32.
     */
    public static function settingsSchema(callable $onSave): array
    {
        $tabs = [];

        foreach (InverterSettings::sections() as $sec) {
            if (isset($sec['tabs'])) {
                // Section có subtab (vd Cài đặt Pin: Sạc/Xả) → tab cha chứa Tabs con.
                $subTabs = [];
                foreach ($sec['tabs'] as $tab) {
                    $tabSchema = [];
                    foreach ($tab['items'] as $it) {
                        foreach (self::expandItem($it) as $f) {
                            $tabSchema[] = self::field($f, $onSave);
                        }
                    }
                    $subTabs[] = Tabs\Tab::make($tab['name'])->schema($tabSchema);
                }

                $tabs[] = Tabs\Tab::make($sec['title'])->schema([
                    Tabs::make('sub_'.strtolower(str_replace(' ', '_', $sec['title'])))
                        ->schema($subTabs),
                ]);
            } else {
                $tabSchema = [];
                foreach ($sec['items'] as $it) {
                    foreach (self::expandItem($it) as $f) {
                        $tabSchema[] = self::field($f, $onSave);
                    }
                }

                $tabs[] = Tabs\Tab::make($sec['title'])->schema($tabSchema);
            }
        }

        return [
            Tabs::make('settings_tabs')->schema($tabs)->columnSpanFull(),
        ];
    }

    /**
     * Mở rộng 1 item thành các field thực (timerange → 2 field time).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function expandItem(array $it): array
    {
        if (($it['type'] ?? null) === 'timerange') {
            $vis = array_intersect_key($it, array_flip(['showIf', 'show', 'showAc', 'showGen']));

            return [
                ['type' => 'time', 'reg' => $it['start'], 'label' => $it['label'].' — bắt đầu'] + $vis,
                ['type' => 'time', 'reg' => $it['end'], 'label' => $it['label'].' — kết thúc'] + $vis,
            ];
        }

        return [$it];
    }

    /**
     * Tạo Filament component từ 1 field (với nút lưu từng field / toggle tự lưu).
     */
    private static function field(array $f, callable $onSave): mixed
    {
        $key = isset($f['bit']) ? "reg_{$f['reg']}_b{$f['bit']}" : "reg_{$f['reg']}";
        $visibility = fn (Get $get): bool => self::visible($f, $get);

        return match ($f['type']) {
            'switch' => Toggle::make($key)
                ->label($f['label'])
                ->live()
                ->afterStateUpdated(fn ($state): mixed => $onSave($key, $state))
                ->visible($visibility),
            'select' => Select::make($key)
                ->label($f['label'])
                ->options($f['options'] ?? [])
                ->suffixAction(self::saveFieldAction($key, $onSave))
                ->visible($visibility),
            'time' => TextInput::make($key)
                ->label($f['label'])
                ->type('time')
                ->suffixAction(self::saveFieldAction($key, $onSave))
                ->visible($visibility),
            'quickcharge' => TextInput::make($key)
                ->label($f['label'])
                ->numeric()
                ->minValue(0)
                ->maxValue(1440)
                ->suffixAction(self::saveFieldAction($key, $onSave))
                ->visible($visibility),
            default => TextInput::make($key)
                ->label($f['label'])
                ->numeric()
                ->minValue($f['min'] ?? null)
                ->maxValue($f['max'] ?? null)
                ->suffix($f['unit'] ?? null)
                ->suffixAction(self::saveFieldAction($key, $onSave))
                ->visible($visibility),
        };
    }

    /**
     * Nút Lưu nhỏ (suffixAction) để ghi ngay field đang chỉnh sửa.
     */
    private static function saveFieldAction(string $key, callable $onSave): FilamentAction
    {
        return FilamentAction::make('save_'.$key)
            ->label('Lưu')
            ->icon('heroicon-m-check')
            ->iconButton()
            ->action(function (Get $get) use ($key, $onSave): void {
                $onSave($key, $get($key));
            });
    }

    /**
     * Ẩn/hiện field theo bitfield phụ thuộc (mirror JS rowVisible trên ESP32).
     */
    private static function visible(array $f, Get $get): bool
    {
        if (isset($f['showIf'])) {
            $toggleKey = $f['showIf'] === 'gridExport' ? 'reg_21_b15' : 'reg_21_b10';
            if (! (bool) $get($toggleKey)) {
                return false;
            }
        }

        if (isset($f['show'])) {
            $mode = (int) $get('reg_120_b4');
            if (! in_array($mode, $f['show'], true)) {
                return false;
            }
        }

        if (isset($f['showAc'])) {
            $type = (int) $get('reg_120_b1');
            if (! in_array($type, $f['showAc'], true)) {
                return false;
            }
        }

        if (isset($f['showGen'])) {
            $gen = (int) $get('reg_120_b7');
            if (! in_array($gen, $f['showGen'], true)) {
                return false;
            }
        }

        return true;
    }
}
