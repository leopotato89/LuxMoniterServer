<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Device;
use App\Services\DeviceSettingsService;
use App\Support\InverterSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

class DeviceSettings extends Page
{
    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.devices.pages.device-settings';

    public Device $device;

    public ?array $data = [];

    /**
     * Giá trị form đã đọc từ thiết bị (để diff khi lưu).
     *
     * @var array<string, mixed>
     */
    public array $current = [];

    public function mount(int|string $record): void
    {
        $this->device = DeviceResource::getEloquentQuery()->findOrFail($record);
        $this->loadFromDevice();
    }

    /**
     * Đọc cấu hình hiện tại từ thiết bị và điền vào form.
     */
    public function loadFromDevice(): void
    {
        try {
            $this->current = app(DeviceSettingsService::class)->read($this->device);
            $this->form->fill($this->current);

            Notification::make()
                ->title('Đã đọc cấu hình từ thiết bị')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Không đọc được cấu hình')
                ->body($e->getMessage().' Thiết bị có thể đang offline.')
                ->danger()
                ->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->buildSchema())
            ->statePath('data');
    }

    /**
     * Lưu các thay đổi xuống thiết bị + ghi audit.
     */
    public function save(): void
    {
        $new = $this->form->getState();

        $audit = app(DeviceSettingsService::class)->save(
            $this->device,
            $new,
            $this->current,
            auth()->user(),
        );

        $this->current = $new;

        $failed = collect($audit)->filter(fn ($c) => $c->status === 'failed')->count();

        Notification::make()
            ->title('Đã lưu cài đặt')
            ->body(count($audit).' mục đã ghi'.($failed ? ", {$failed} mục lỗi" : ''))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reload')
                ->label('Tải lại dữ liệu')
                ->icon('heroicon-m-arrow-path')
                ->action(fn () => $this->loadFromDevice()),
            Action::make('save')
                ->label('Lưu cài đặt')
                ->icon('heroicon-m-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    /**
     * Dựng schema form từ cấu hình field.
     */
    private function buildSchema(): array
    {
        $schema = [];

        foreach (InverterSettings::sections() as $sec) {
            if (isset($sec['tabs'])) {
                $tabs = [];
                foreach ($sec['tabs'] as $tab) {
                    $tabSchema = [];
                    foreach ($tab['items'] as $it) {
                        foreach ($this->itemFields($it) as $f) {
                            $tabSchema[] = $this->field($f);
                        }
                    }
                    $tabs[] = Tabs\Tab::make($tab['name'])->schema($tabSchema);
                }

                $schema[] = Tabs::make('tabs_'.strtolower(str_replace(' ', '_', $sec['title'])))
                    ->schema($tabs)
                    ->columnSpanFull();
            } else {
                $sectionSchema = [];
                foreach ($sec['items'] as $it) {
                    foreach ($this->itemFields($it) as $f) {
                        $sectionSchema[] = $this->field($f);
                    }
                }

                $schema[] = Section::make($sec['title'])->schema($sectionSchema);
            }
        }

        return $schema;
    }

    /**
     * Mở rộng 1 item thành các field thực (timerange → 2 field time).
     */
    private function itemFields(array $it): array
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
     * Tạo Filament component từ 1 field.
     */
    private function field(array $f): mixed
    {
        $key = isset($f['bit']) ? "reg_{$f['reg']}_b{$f['bit']}" : "reg_{$f['reg']}";
        $visibility = fn (Get $get): bool => $this->visible($f, $get);

        return match ($f['type']) {
            'switch' => Toggle::make($key)
                ->label($f['label'])
                ->live()
                ->visible($visibility),
            'select' => Select::make($key)
                ->label($f['label'])
                ->options($f['options'])
                ->live()
                ->visible($visibility),
            'time' => TextInput::make($key)
                ->label($f['label'])
                ->type('time')
                ->visible($visibility),
            'quickcharge' => TextInput::make($key)
                ->label($f['label'])
                ->numeric()
                ->minValue(0)
                ->maxValue(1440)
                ->visible($visibility),
            default => TextInput::make($key)
                ->label($f['label'])
                ->numeric()
                ->minValue($f['min'] ?? null)
                ->maxValue($f['max'] ?? null)
                ->suffix($f['unit'] ?? null)
                ->visible($visibility),
        };
    }

    /**
     * Ẩn/hiện field theo bitfield phụ thuộc (mirror JS rowVisible trên ESP32).
     */
    private function visible(array $f, Get $get): bool
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
