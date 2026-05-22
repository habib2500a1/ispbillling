<?php

namespace App\Support\Rbac;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Pages\OperationsHub;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ResellerResource;
use App\Models\User;

/**
 * Maps Spatie permissions to dashboard widgets, sidebar modules, and home URLs.
 */
final class StaffCapability
{
    public function __construct(
        private readonly ?User $user,
    ) {}

    public static function for(?User $user): self
    {
        return new self($user);
    }

    /** Roles that bypass permission checks (full ISP admin access). */
    public const FULL_ACCESS_ROLES = ['super-admin', 'isp-admin', 'admin'];

    public function isTenantAdmin(): bool
    {
        return $this->user !== null && $this->user->hasRole(self::FULL_ACCESS_ROLES);
    }

    public function can(string $permission): bool
    {
        if ($this->user === null) {
            return false;
        }

        if ($this->isTenantAdmin()) {
            return true;
        }

        return $this->user->can($permission);
    }

    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canCustomers(): bool
    {
        return $this->can('customers.view')
            || CustomerResource::canViewAny();
    }

    public function canBilling(): bool
    {
        return $this->canAny(['billing.view', 'billing.manage', 'billing.analytics'])
            || InvoiceResource::canViewAny();
    }

    public function canPayments(): bool
    {
        return $this->canAny(['payments.view', 'payments.add', 'collections.view'])
            || PaymentResource::canViewAny();
    }

    public function canCollect(): bool
    {
        return $this->canAny(['payments.add', 'collections.view', 'billing.view']);
    }

    public function canMikrotik(): bool
    {
        return $this->canAny(['mikrotik.view', 'mikrotik.manage', 'network.monitor', 'network.traffic']);
    }

    public function canOlt(): bool
    {
        return $this->canAny(['olts.view', 'olts.manage', 'onu.signal', 'devices.view']);
    }

    public function canNetwork(): bool
    {
        return $this->canMikrotik() || $this->canOlt();
    }

    public function canSupport(): bool
    {
        return $this->can('support.view');
    }

    public function canReports(): bool
    {
        return $this->canAny(['reports.view', 'reports.revenue', 'reports.analytics', 'billing.analytics']);
    }

    public function canResellers(): bool
    {
        return $this->can('resellers.view') || ResellerResource::canViewAny();
    }

    public function canAccounting(): bool
    {
        return $this->canAny(['accounting.view', 'accounting.manage', 'accounting.ledger', 'payroll.view']);
    }

    public function canSms(): bool
    {
        return $this->canAny(['system.notifications', 'integrations.view']);
    }

    public function canInventory(): bool
    {
        return $this->can('inventory.view');
    }

    public function canStaffModule(): bool
    {
        return $this->canAny(['staff.view', 'staff.manage', 'staff.kpi']);
    }

    public function canHrm(): bool
    {
        return $this->canStaffModule() || $this->can('payroll.view');
    }

    public function canSystemSettings(): bool
    {
        return $this->canAny(['system.settings', 'integrations.view', 'audit.view']);
    }

    public function canAccessAnyModule(): bool
    {
        return $this->isTenantAdmin()
            || $this->canCustomers()
            || $this->canBilling()
            || $this->canPayments()
            || $this->canNetwork()
            || $this->canSupport()
            || $this->canReports()
            || $this->canResellers()
            || $this->canAccounting()
            || $this->canSms()
            || $this->canInventory()
            || $this->canHrm()
            || $this->canSystemSettings();
    }

    public function canAccessModuleGroup(string $group): bool
    {
        return match ($group) {
            'Subscribers' => $this->canCustomers(),
            'Billing' => $this->canBilling(),
            'Payments' => $this->canPayments(),
            'Network' => $this->canNetwork(),
            'Support' => $this->canSupport(),
            'HR & Payroll' => $this->canHrm(),
            'Inventory' => $this->canInventory(),
            'Inventory Pro' => $this->canInventory(),
            'Finance' => $this->canAccounting(),
            'Resellers' => $this->canResellers(),
            'Reports' => $this->canReports(),
            'System' => $this->isTenantAdmin() || $this->canSystemSettings(),
            default => $this->isTenantAdmin(),
        };
    }

    public function canSeeBillingWidget(): bool
    {
        return $this->canBilling() || $this->canPayments() || $this->canCollect();
    }

    public function canSeeOperationsWidget(): bool
    {
        return $this->canCustomers()
            || $this->canBilling()
            || $this->canPayments()
            || $this->canNetwork()
            || $this->canSupport();
    }

    public function canSeeRevenueChart(): bool
    {
        return $this->canBilling() || $this->canReports();
    }

    public function canSeeOnlineChart(): bool
    {
        return $this->canMikrotik() || $this->canNetwork();
    }

    public function canSeeCommandStrip(): bool
    {
        return $this->canCollect() || $this->canCustomers() || $this->canNetwork();
    }

    /**
     * @param  class-string  $widgetClass
     */
    public function canSeeWidget(string $widgetClass): bool
    {
        return match ($widgetClass) {
            \App\Filament\Widgets\PendingMfsVerifyAlertWidget::class => $this->canSeeBillingWidget() || $this->canPayments(),
            \App\Filament\Widgets\BillingExecutiveDashboardWidget::class => $this->canSeeBillingWidget(),
            \App\Filament\Widgets\OperationsCommandCenterWidget::class => $this->canSeeOperationsWidget(),
            \App\Filament\Widgets\DashboardCommandStripWidget::class => $this->canSeeCommandStrip(),
            \App\Filament\Widgets\RevenueTrendChartWidget::class => $this->canSeeRevenueChart(),
            \App\Filament\Widgets\OnlineUsersChartWidget::class => $this->canSeeOnlineChart(),
            default => $this->isTenantAdmin(),
        };
    }

    /**
     * @return list<class-string>
     */
    public function allowedDashboardWidgets(): array
    {
        $allowed = [];

        foreach (\App\Services\Dashboard\DashboardPreferencesService::DEFAULT_WIDGETS as $widget) {
            if ($this->canSeeWidget($widget)) {
                $allowed[] = $widget;
            }
        }

        return $allowed;
    }

    /**
     * When the main dashboard has no permitted widgets, send staff to their module home.
     */
    public function preferredHomeUrl(): ?string
    {
        if ($this->user === null) {
            return null;
        }

        if ($this->allowedDashboardWidgets() !== []) {
            return null;
        }

        if ($this->canCollect() && BillCollectionDesk::canAccess()) {
            return BillCollectionDesk::getUrl();
        }

        if ($this->canSupport() && SupportHub::canAccess()) {
            return SupportHub::getUrl();
        }

        if ($this->canCustomers() && CustomerResource::canViewAny()) {
            return CustomerResource::getUrl('index');
        }

        if ($this->canNetwork()) {
            if (OnlineClientsMonitoring::canAccess()) {
                return OnlineClientsMonitoring::getUrl();
            }

            if (OperationsHub::canAccess()) {
                return OperationsHub::getUrl();
            }

            if (OpticalMonitoringHub::canAccess()) {
                return OpticalMonitoringHub::getUrl();
            }
        }

        if ($this->canBilling() && BillingDashboard::canAccess()) {
            return BillingDashboard::getUrl();
        }

        return null;
    }
}
