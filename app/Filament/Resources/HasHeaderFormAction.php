<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;

trait HasHeaderFormAction
{
    public function getHeaderActions(): array
    {
        return array_merge(method_exists($this, '_getHeaderActions') ? $this->_getHeaderActions() : [], parent::getFormActions());
    }

    public function getFormActions(): array
    {
        return [];
    }

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label(__('filament-panels::resources/pages/create-record.form.actions.create.label'))
            ->action('create')
            ->keyBindings(['mod+s']);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }
}
