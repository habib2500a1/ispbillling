<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\PayrollRunResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected static string $view = 'filament.resources.employee-resource.pages.list-employees';

    /**
     * @return array<string, int|float>
     */
    public function getEmployeeStats(): array
    {
        $base = Employee::query();

        return [
            'total' => (int) (clone $base)->count(),
            'active' => (int) (clone $base)->where('is_active', true)->count(),
            'inactive' => (int) (clone $base)->where('is_active', false)->count(),
            'monthly_gross' => (float) (clone $base)->where('is_active', true)->sum('base_salary'),
            'wallet_total' => (float) (clone $base)->sum('wallet_balance'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('salary_module')
                ->label('Salary module')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(PayrollRunResource::getUrl()),
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            Actions\CreateAction::make()
                ->label('Add employee')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'employees-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['ID', 'Name', 'Designation', 'Department', 'Join date', 'Salary', 'Wallet', 'Status', 'Phone', 'Email']);

            $this->getTableQuery()
                ->orderBy('name')
                ->chunk(200, function ($employees) use ($handle): void {
                    foreach ($employees as $employee) {
                        /** @var Employee $employee */
                        fputcsv($handle, [
                            $employee->employee_code,
                            $employee->name,
                            $employee->designation,
                            $employee->department,
                            $employee->join_date?->format('Y-m-d'),
                            $employee->base_salary,
                            $employee->wallet_balance,
                            $employee->is_active ? 'Active' : 'Inactive',
                            $employee->phone,
                            $employee->email,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
