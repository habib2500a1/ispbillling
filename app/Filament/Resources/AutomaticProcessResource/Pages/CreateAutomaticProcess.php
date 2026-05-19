<?php

namespace App\Filament\Resources\AutomaticProcessResource\Pages;

use App\Filament\Resources\AutomaticProcessResource;
use App\Services\Automation\AutomaticProcessScheduler;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAutomaticProcess extends CreateRecord
{
    protected static string $resource = AutomaticProcessResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['slug'] ?? null) && filled($data['name'] ?? null)) {
            $base = Str::slug($data['name']);
            $slug = $base;
            $n = 1;
            while (\App\Models\AutomaticProcess::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$n;
                $n++;
            }
            $data['slug'] = $slug;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $scheduler = app(AutomaticProcessScheduler::class);
        $this->record->forceFill([
            'next_run_at' => $scheduler->computeNextRunAt($this->record),
        ])->save();
    }
}
