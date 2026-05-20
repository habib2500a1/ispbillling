<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Filament\Resources\PayrollRunResource;
use App\Models\Employee;
use App\Models\PayrollItem;
use Filament\Pages\Page;

class AccountsMySalaryPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.accounts-my-salary';

    protected static ?string $navigationLabel = 'My salary';

    protected static ?string $title = 'My salary';

    protected static ?string $slug = 'accounts-my-salary';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->can('payroll.view')
            || \App\Support\Rbac\StaffCapability::for(auth()->user())->canAccounting();
    }

    public function getEmployeeProperty(): ?Employee
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        return Employee::query()
            ->where(function ($q) use ($user): void {
                if (filled($user->email)) {
                    $q->where('email', $user->email);
                }
                $q->orWhere('name', $user->name);
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPayrollHistoryProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        return PayrollItem::query()
            ->with('payrollRun')
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (PayrollItem $item): array => [
                'period' => $item->payrollRun?->periodLabel() ?? '—',
                'gross' => (float) $item->gross_salary,
                'deductions' => (float) $item->deductions,
                'net' => (float) $item->net_salary,
                'status' => $item->payrollRun?->status ?? '—',
            ])
            ->all();
    }

    public function getPayrollUrlProperty(): string
    {
        return PayrollRunResource::getUrl();
    }
}
