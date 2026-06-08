<?php

namespace App\Services\IspOs;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Employee;
use App\Models\FiberPlantNode;
use App\Models\InternalTask;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Support\TenantResolver;

final class GlobalOperationsSearchService
{
    /**
     * @return list<array{label: string, group: string, url: string, meta?: string}>
     */
    public function search(string $query, int $limit = 20): array
    {
        $q = trim($query);
        if (strlen($q) < 2) {
            return [];
        }

        $tenantId = TenantResolver::requiredTenantId();
        $like = '%'.$q.'%';
        $results = [];

        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like, $q): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('radius_username', 'like', $like)
                    ->orWhere('mikrotik_secret_name', 'like', $like);
                if (is_numeric($q)) {
                    $builder->orWhere('id', (int) $q);
                }
            })
            ->limit(8)
            ->get(['id', 'name', 'customer_code', 'phone'])
            ->each(function (Customer $c) use (&$results): void {
                $results[] = [
                    'label' => $c->name.' ('.$c->customer_code.')',
                    'group' => 'Customer',
                    'url' => \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $c->id]),
                    'meta' => $c->phone,
                ];
            });

        Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->where(function ($builder) use ($like): void {
                $builder->where('serial_number', 'like', $like)
                    ->orWhere('mac_address', 'like', $like)
                    ->orWhere('display_name', 'like', $like);
            })
            ->limit(6)
            ->get(['id', 'serial_number', 'mac_address', 'olt_id'])
            ->each(function (Device $d) use (&$results): void {
                $results[] = [
                    'label' => 'ONU '.($d->serial_number ?: $d->mac_address ?: '#'.$d->id),
                    'group' => 'ONU',
                    'url' => $d->olt_id
                        ? \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $d->olt_id])
                        : \App\Filament\Pages\OpticalMonitoringHub::getUrl(),
                ];
            });

        Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where(function ($builder) use ($like): void {
                $builder->where('display_name', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('management_ip', 'like', $like);
            })
            ->limit(4)
            ->get(['id', 'display_name', 'serial_number', 'type'])
            ->each(function (Device $d) use (&$results): void {
                $results[] = [
                    'label' => 'OLT '.$d->adminLabel(),
                    'group' => 'OLT',
                    'url' => \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $d->id]),
                ];
            });

        MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)->orWhere('host', 'like', $like);
            })
            ->limit(4)
            ->get(['id', 'name'])
            ->each(function (MikrotikServer $r) use (&$results): void {
                $results[] = [
                    'label' => 'Router '.$r->name,
                    'group' => 'Router',
                    'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('edit', ['record' => $r->id]),
                ];
            });

        if (is_numeric($q)) {
            SupportTicket::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', (int) $q)
                ->limit(1)
                ->get(['id', 'subject'])
                ->each(function (SupportTicket $t) use (&$results): void {
                    $results[] = [
                        'label' => 'Ticket #'.$t->id.' — '.($t->subject ?? ''),
                        'group' => 'Ticket',
                        'url' => \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $t->id]),
                    ];
                });
        }

        SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('subject', 'like', $like)
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'subject'])
            ->each(function (SupportTicket $t) use (&$results): void {
                $results[] = [
                    'label' => 'Ticket #'.$t->id.' — '.($t->subject ?? ''),
                    'group' => 'Ticket',
                    'url' => \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $t->id]),
                ];
            });

        Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $like)
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'invoice_number', 'total'])
            ->each(function (Invoice $inv) use (&$results): void {
                $results[] = [
                    'label' => $inv->invoice_number,
                    'group' => 'Invoice',
                    'url' => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $inv->id]),
                    'meta' => number_format((float) $inv->total, 0).' BDT',
                ];
            });

        Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('receipt_number', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'receipt_number', 'amount'])
            ->each(function (Payment $pay) use (&$results): void {
                $results[] = [
                    'label' => $pay->receipt_number ?: 'Payment #'.$pay->id,
                    'group' => 'Payment',
                    'url' => \App\Filament\Resources\PaymentResource::getUrl('edit', ['record' => $pay->id]),
                    'meta' => number_format((float) $pay->amount, 0).' BDT',
                ];
            });

        Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('employee_code', 'like', $like)
                    ->orWhere('department', 'like', $like);
            })
            ->limit(4)
            ->get(['id', 'name', 'employee_code'])
            ->each(function (Employee $e) use (&$results): void {
                $results[] = [
                    'label' => $e->name,
                    'group' => 'Employee',
                    'url' => \App\Filament\Resources\EmployeeResource::getUrl('edit', ['record' => $e->id]),
                    'meta' => $e->employee_code,
                ];
            });

        InternalTask::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('title', 'like', $like)
            ->orderByDesc('id')
            ->limit(3)
            ->get(['id', 'title', 'status'])
            ->each(function (InternalTask $task) use (&$results): void {
                $results[] = [
                    'label' => $task->title,
                    'group' => 'Task',
                    'url' => \App\Filament\Resources\InternalTaskResource::getUrl('edit', ['record' => $task->id]),
                    'meta' => $task->status,
                ];
            });

        FiberPlantNode::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('type', 'like', $like);
            })
            ->whereIn('type', ['splitter', 'junction', 'pop'])
            ->limit(4)
            ->get(['id', 'name', 'type'])
            ->each(function (FiberPlantNode $n) use (&$results): void {
                $results[] = [
                    'label' => ucfirst((string) $n->type).' '.$n->name,
                    'group' => 'GIS',
                    'url' => \App\Filament\Pages\FiberPlantMap::getUrl(),
                ];
            });

        return array_slice($results, 0, $limit);
    }
}
