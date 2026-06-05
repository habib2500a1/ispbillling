<?php

namespace App\Filament\Concerns;

use App\Filament\Pages\HrPayrollHub;

trait ChecksHrAccess
{
    public static function canAccess(): bool
    {
        return HrPayrollHub::canAccess();
    }

    public static function canManagePayroll(): bool
    {
        return HrPayrollHub::canAccess()
            && auth()->check()
            && (
                auth()->user()->hasRole(['super-admin', 'isp-admin', 'admin'])
                || auth()->user()->can('payroll.manage')
                || auth()->user()->can('accounting.payroll')
            );
    }
}
