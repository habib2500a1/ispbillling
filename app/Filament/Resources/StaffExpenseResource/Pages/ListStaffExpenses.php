<?php

namespace App\Filament\Resources\StaffExpenseResource\Pages;

use App\Filament\Resources\StaffExpenseResource;
use App\Models\StaffExpense;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStaffExpenses extends ListRecords
{
    protected static string $resource = StaffExpenseResource::class;

    public function mount(): void
    {
        parent::mount();
        app(\App\Services\Expenses\StaffExpenseService::class)
            ->ensureDefaultCategories(\App\Support\TenantResolver::requiredTenantId());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New expense'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StaffExpense::STATUS_PENDING))
                ->badge(fn (): int => StaffExpense::query()->where('status', StaffExpense::STATUS_PENDING)->count()),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StaffExpense::STATUS_APPROVED)),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StaffExpense::STATUS_REJECTED)),
        ];
    }
}
