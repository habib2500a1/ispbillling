<?php

namespace Tests\Feature;

use App\Support\HrmSidebarRegistry;
use Tests\TestCase;

class HrmSidebarRegistryTest extends TestCase
{
    public function test_definitions_match_legacy_hr_menu_structure(): void
    {
        $labels = array_column(HrmSidebarRegistry::definitions(), 'label');

        $this->assertSame([
            'HR Dashboard',
            'Employees',
            'Attendance',
            'Leave Management',
            'Advance Salary',
            'Payroll Generation',
            'Salary Policies',
            'HR Reports',
        ], $labels);
    }

    public function test_group_label_is_hr_management(): void
    {
        $this->assertSame('HR Management', HrmSidebarRegistry::GROUP_LABEL);
    }
}
