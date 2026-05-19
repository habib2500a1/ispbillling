<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $names = [
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'devices.view',
            'devices.manage',
            'olts.view',
            'olts.manage',
            'ports.view',
            'ports.manage',
            'billing.view',
            'billing.manage',
            'integrations.view',
            'integrations.manage',
            'mikrotik.view',
            'mikrotik.manage',
            'resellers.view',
            'resellers.manage',
            'reports.view',
            'support.view',
            'support.manage',
            'support.assign',
            'knowledge.manage',
            'outages.manage',
            'field_visits.manage',
        ];

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    public function down(): void
    {
        // Intentionally left blank: do not delete permissions on rollback.
    }
};
