<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksHrAccess;
use App\Filament\Resources\PayrollRunResource;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Accounting\PayrollService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;

class HrPayrollGenerationPage extends Page
{
    use ChecksHrAccess;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.hr-payroll-generation';

    protected static ?string $navigationLabel = 'Payroll Generation';

    protected static ?string $title = 'Payroll & Salary';

    protected static ?string $slug = 'hr-payroll-generation';

    protected static bool $shouldRegisterNavigation = false;

    public int $periodMonth;

    public int $periodYear;

    public string $bonus = 'none';

    public ?int $runId = null;

    public function mount(): void
    {
        $this->periodMonth = (int) now()->month;
        $this->periodYear = (int) now()->year;
        $this->loadRun();
    }

    public function updatedPeriodMonth(): void
    {
        $this->loadRun();
    }

    public function updatedPeriodYear(): void
    {
        $this->loadRun();
    }

    public function loadRun(): void
    {
        $run = PayrollRun::query()
            ->where('period_month', $this->periodMonth)
            ->where('period_year', $this->periodYear)
            ->first();

        $this->runId = $run?->id;
    }

    public function getPeriodLabelProperty(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->periodMonth, 1, $this->periodYear));
    }

    public function getCurrentRunProperty(): ?PayrollRun
    {
        if ($this->runId === null) {
            return null;
        }

        return PayrollRun::query()->with('items.employee')->find($this->runId);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, PayrollItem>|null
     */
    public function getSheetItemsProperty()
    {
        $run = $this->currentRun;
        if ($run === null) {
            return null;
        }

        return PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->with('employee')
            ->orderBy('employee_id')
            ->paginate(25);
    }

    public function generatePayroll(): void
    {
        if (! static::canManagePayroll()) {
            return;
        }

        $run = app(PayrollService::class)->generateDraft(
            $this->periodMonth,
            $this->periodYear,
            null,
            ['bonus' => $this->bonus],
        );

        $this->runId = $run->id;

        Notification::make()
            ->title('Payroll generated')
            ->body($run->periodLabel().' — net '.number_format((float) $run->total_net, 2).' BDT')
            ->success()
            ->send();
    }

    public function viewRun(): void
    {
        $run = $this->currentRun;
        if ($run === null) {
            Notification::make()->title('No payroll for this month')->warning()->send();

            return;
        }

        $this->redirect(PayrollRunResource::getUrl('view', ['record' => $run]));
    }
}
