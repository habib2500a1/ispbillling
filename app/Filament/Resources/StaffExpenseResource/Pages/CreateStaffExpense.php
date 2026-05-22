<?php

namespace App\Filament\Resources\StaffExpenseResource\Pages;

use App\Filament\Resources\StaffExpenseResource;
use App\Services\Expenses\StaffExpenseService;
use App\Support\TenantResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffExpense extends CreateRecord
{
    protected static string $resource = StaffExpenseResource::class;

    public function mount(): void
    {
        parent::mount();
        app(StaffExpenseService::class)->ensureDefaultCategories(TenantResolver::requiredTenantId());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['expense_source'] ?? '') !== \App\Models\StaffExpense::SOURCE_VENDOR) {
            $data['vendor_id'] = null;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $expense = app(StaffExpenseService::class)->submit([
            'expense_source' => $data['expense_source'],
            'vendor_id' => ! empty($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            'category_id' => (int) $data['category_id'],
            'amount' => (float) $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'expense_date' => $data['expense_date'] ?? null,
            'description' => $data['description'] ?? null,
            'proof_path' => is_array($data['proof_path'] ?? null)
                ? ($data['proof_path'][0] ?? null)
                : ($data['proof_path'] ?? null),
        ]);

        Notification::make()
            ->title($expense->status === 'pending' ? 'Expense submitted for approval' : 'Expense recorded')
            ->success()
            ->send();

        return $expense;
    }

    protected function getRedirectUrl(): string
    {
        return StaffExpenseResource::getUrl('index');
    }
}
