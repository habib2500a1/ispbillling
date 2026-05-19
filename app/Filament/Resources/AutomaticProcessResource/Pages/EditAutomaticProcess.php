<?php

namespace App\Filament\Resources\AutomaticProcessResource\Pages;

use App\Filament\Resources\AutomaticProcessResource;
use App\Services\Automation\AutomaticProcessScheduler;
use Filament\Resources\Pages\EditRecord;

class EditAutomaticProcess extends EditRecord
{
    protected static string $resource = AutomaticProcessResource::class;

    protected function afterSave(): void
    {
        $scheduler = app(AutomaticProcessScheduler::class);
        $this->record->forceFill([
            'next_run_at' => $scheduler->computeNextRunAt($this->record),
        ])->save();
    }
}
