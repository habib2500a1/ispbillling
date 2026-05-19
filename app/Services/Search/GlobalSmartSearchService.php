<?php

namespace App\Services\Search;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Payment;
use App\Models\PppSessionLog;
use App\Models\SupportTicket;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final class GlobalSmartSearchService
{
    /**
     * @param  list<string>  $columns
     */
    private function applyLikeAny(Builder $query, array $columns, string $like): void
    {
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $b) use ($columns, $like, $op): void {
            foreach ($columns as $i => $column) {
                if ($i === 0) {
                    $b->where($column, $op, $like);
                } else {
                    $b->orWhere($column, $op, $like);
                }
            }
        });
    }

    /**
     * @return array{url: string, view_url: string, edit_url: string, pay_url: string}
     */
    private function customerLinks(Customer $customer): array
    {
        $viewUrl = CustomerResource::getUrl('view', ['record' => $customer]);
        $editUrl = CustomerResource::getUrl('edit', ['record' => $customer]);
        $payUrl = BillCollectionDesk::getUrl(['customer' => $customer->id]);

        return [
            'url' => $viewUrl,
            'view_url' => $viewUrl,
            'edit_url' => $editUrl,
            'pay_url' => $payUrl,
        ];
    }

    /**
     * @return list<array{type: string, label: string, sublabel: string, url: string, view_url?: string, edit_url?: string, pay_url?: string}>
     */
    public function search(string $query, int $limit = 12): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $tenantId = TenantResolver::requiredTenantId();
        $like = '%'.$q.'%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $results = [];

        try {
            $customerQuery = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId);
            $op = $customerQuery->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $customerQuery->where(function (Builder $b) use ($like, $q, $digits, $op): void {
                $cols = [
                    'name',
                    'customer_code',
                    'phone',
                    'email',
                    'address',
                    'mikrotik_secret_name',
                    'radius_username',
                    'nid_number',
                ];
                $started = false;

                if (ctype_digit($q)) {
                    $b->where('id', (int) $q);
                    $started = true;
                }

                foreach ($cols as $col) {
                    if (! $started) {
                        $b->where($col, $op, $like);
                        $started = true;
                    } else {
                        $b->orWhere($col, $op, $like);
                    }
                }

                if ($digits !== '' && strlen($digits) >= 3) {
                    $b->orWhere('phone', 'like', '%'.$digits.'%');
                }

                $b->orWhereHas('area', fn (Builder $aq) => $aq->where('name', $op, $like))
                    ->orWhereHas('zone', fn (Builder $zq) => $zq->where('name', $op, $like))
                    ->orWhereHas('subzone', fn (Builder $sq) => $sq->where('name', $op, $like));
            });

            $customers = $customerQuery
                ->with(['area:id,name', 'zone:id,name', 'subzone:id,name'])
                ->limit($limit)
                ->get([
                    'id',
                    'name',
                    'customer_code',
                    'phone',
                    'address',
                    'area_id',
                    'zone_id',
                    'subzone_id',
                    'mikrotik_secret_name',
                    'status',
                    'is_ppp_online',
                ]);

            foreach ($customers as $c) {
                $links = $this->customerLinks($c);
                $address = $c->formattedAddress();
                $results[] = [
                    'type' => 'customer',
                    'label' => ($c->customer_code ?: '#'.$c->id).' — '.$c->name,
                    'sublabel' => trim(
                        'ID '.$c->id
                        .' · Phone '.($c->phone ?: '—')
                        .' · Address '.($address !== '—' ? $address : '—')
                        .' · PPP '.($c->mikrotik_secret_name ?: '—')
                        .($c->is_ppp_online ? ' · online' : '')
                        .' · '.($c->status ?? '—')
                    ),
                    ...$links,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('smart_search.customers_failed', ['error' => $e->getMessage()]);
        }

        if (count($results) < $limit) {
            try {
                $sessions = PppSessionLog::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->where('username', 'like', $like)
                    ->with('customer:id,name,customer_code,phone')
                    ->limit($limit - count($results))
                    ->get(['id', 'username', 'customer_id', 'framed_ip']);

                foreach ($sessions as $s) {
                    $cust = $s->customer;
                    if ($cust instanceof Customer) {
                        $links = $this->customerLinks($cust);
                        $results[] = [
                            'type' => 'online',
                            'label' => $s->username,
                            'sublabel' => ($cust->customer_code ? $cust->customer_code.' · ' : '')
                                .$cust->name
                                .($s->framed_ip ? ' · '.$s->framed_ip : ''),
                            ...$links,
                        ];
                    } else {
                        $results[] = [
                            'type' => 'online',
                            'label' => $s->username,
                            'sublabel' => 'Unmatched'.($s->framed_ip ? ' · '.$s->framed_ip : ''),
                            'url' => \App\Filament\Pages\BandwidthMonitor::getUrl(),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.sessions_failed', ['error' => $e->getMessage()]);
            }
        }

        if (count($results) < $limit) {
            try {
                $invoiceQuery = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId);
                $this->applyLikeAny($invoiceQuery, ['invoice_number'], $like);
                $invoices = $invoiceQuery
                    ->limit($limit - count($results))
                    ->get(['id', 'invoice_number', 'total', 'status']);

                foreach ($invoices as $inv) {
                    $results[] = [
                        'type' => 'invoice',
                        'label' => 'Invoice #'.$inv->invoice_number,
                        'sublabel' => number_format((float) $inv->total, 0).' BDT · '.$inv->status,
                        'url' => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $inv]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.invoices_failed', ['error' => $e->getMessage()]);
            }
        }

        if (count($results) < $limit) {
            try {
                $paymentQuery = Payment::withoutGlobalScopes()->where('tenant_id', $tenantId);
                $this->applyLikeAny($paymentQuery, ['reference', 'gateway_transaction_id', 'receipt_number'], $like);
                $payments = $paymentQuery
                    ->limit($limit - count($results))
                    ->get(['id', 'reference', 'amount', 'status']);

                foreach ($payments as $p) {
                    $results[] = [
                        'type' => 'payment',
                        'label' => $p->reference ?: 'Payment #'.$p->id,
                        'sublabel' => number_format((float) $p->amount, 0).' BDT · '.$p->status,
                        'url' => \App\Filament\Resources\PaymentResource::getUrl('edit', ['record' => $p]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.payments_failed', ['error' => $e->getMessage()]);
            }
        }

        if (count($results) < $limit) {
            try {
                $ticketQuery = SupportTicket::withoutGlobalScopes()->where('tenant_id', $tenantId);
                $this->applyLikeAny($ticketQuery, ['ticket_number', 'subject'], $like);
                $tickets = $ticketQuery
                    ->limit($limit - count($results))
                    ->get(['id', 'ticket_number', 'subject', 'status']);

                foreach ($tickets as $t) {
                    $results[] = [
                        'type' => 'ticket',
                        'label' => '#'.$t->ticket_number.' — '.$t->subject,
                        'sublabel' => $t->status,
                        'url' => \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $t]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.tickets_failed', ['error' => $e->getMessage()]);
            }
        }

        if (count($results) < $limit) {
            try {
                $onuQuery = Device::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'onu');
                $this->applyLikeAny($onuQuery, ['serial_number', 'management_ip', 'mac_address'], $like);
                $onus = $onuQuery
                    ->limit($limit - count($results))
                    ->get(['id', 'serial_number', 'management_ip', 'status']);

                foreach ($onus as $onu) {
                    $results[] = [
                        'type' => 'onu',
                        'label' => 'ONU '.($onu->serial_number ?: '#'.$onu->id),
                        'sublabel' => ($onu->management_ip ?? '—').' · '.($onu->status ?? 'unknown'),
                        'url' => \App\Filament\Resources\DeviceResource::getUrl('edit', ['record' => $onu]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.onu_failed', ['error' => $e->getMessage()]);
            }
        }

        if (count($results) < $limit) {
            try {
                $routerQuery = MikrotikServer::withoutGlobalScopes()->where('tenant_id', $tenantId);
                $this->applyLikeAny($routerQuery, ['name', 'host'], $like);
                $routers = $routerQuery
                    ->limit($limit - count($results))
                    ->get(['id', 'name', 'host', 'last_api_status']);

                foreach ($routers as $r) {
                    $results[] = [
                        'type' => 'router',
                        'label' => $r->name,
                        'sublabel' => $r->host.' · '.($r->last_api_status ?? 'unknown'),
                        'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('edit', ['record' => $r]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('smart_search.routers_failed', ['error' => $e->getMessage()]);
            }
        }

        return array_slice($results, 0, $limit);
    }
}
