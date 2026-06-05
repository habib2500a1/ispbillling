<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Pages\HrPayrollGenerationPage;
use App\Filament\Resources\PayrollRunResource;
use Filament\Resources\Pages\ListRecords;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    public function mount(): void
    {
        $this->redirect(HrPayrollGenerationPage::getUrl());
    }
}
