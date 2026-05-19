<?php

namespace App\Models\Concerns;

use App\Models\Area;
use App\Models\Customer;
use App\Models\BandwidthAbuseAlert;
use App\Models\BandwidthSample;
use App\Models\BandwidthUsageDaily;
use App\Models\CustomerContact;
use App\Models\PppSessionLog;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Models\Device;
use App\Models\FieldVisit;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\OltPort;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerCommission;
use App\Models\ResellerTerritory;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketMessageAttachment;
use App\Models\SupportTicketUpload;
use App\Models\Subzone;
use App\Models\Tenant;
use App\Models\Zone;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (TenantResolver::isAuthRecursionGuarded()) {
                return;
            }

            if (TenantResolver::applyTenantScope()) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    TenantResolver::currentTenantId()
                );
            }
        });

        static::creating(function (Model $model): void {
            if ($model instanceof Payment && $model->customer_id) {
                $model->setAttribute(
                    'tenant_id',
                    (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'),
                );

                return;
            }

            if ($model instanceof Invoice && $model->customer_id) {
                $model->setAttribute(
                    'tenant_id',
                    (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'),
                );

                return;
            }

            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            if ($model instanceof Zone && $model->area_id) {
                $model->setAttribute('tenant_id', (int) Area::withoutGlobalScopes()->whereKey($model->area_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof Subzone && $model->zone_id) {
                $model->setAttribute('tenant_id', (int) Zone::withoutGlobalScopes()->whereKey($model->zone_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof Reseller && $model->parent_id) {
                $model->setAttribute('tenant_id', (int) Reseller::withoutGlobalScopes()->whereKey($model->parent_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof ResellerTerritory && $model->reseller_id) {
                $model->setAttribute('tenant_id', (int) Reseller::withoutGlobalScopes()->whereKey($model->reseller_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof ResellerBalanceTransfer && $model->to_reseller_id) {
                $model->setAttribute('tenant_id', (int) Reseller::withoutGlobalScopes()->whereKey($model->to_reseller_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof ResellerCommission && $model->reseller_id) {
                $model->setAttribute('tenant_id', (int) Reseller::withoutGlobalScopes()->whereKey($model->reseller_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof CustomerContact && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof CustomerDocument && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof CustomerNote && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof BandwidthSample && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof PppSessionLog && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof BandwidthUsageDaily && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof BandwidthAbuseAlert && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof OltPort && $model->device_id) {
                $model->setAttribute('tenant_id', (int) Device::withoutGlobalScopes()->whereKey($model->device_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof Device && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof Device && $model->olt_id && ! $model->customer_id) {
                $tid = Device::withoutGlobalScopes()->whereKey($model->olt_id)->value('tenant_id');
                if ($tid !== null) {
                    $model->setAttribute('tenant_id', (int) $tid);

                    return;
                }
            }

            if ($model instanceof FieldVisit && $model->support_ticket_id) {
                $model->setAttribute('tenant_id', (int) SupportTicket::withoutGlobalScopes()->whereKey($model->support_ticket_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof SupportTicketMessageAttachment && $model->support_ticket_message_id) {
                $model->setAttribute('tenant_id', (int) SupportTicketMessage::withoutGlobalScopes()->whereKey($model->support_ticket_message_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof Outage && $model->area_id) {
                $model->setAttribute('tenant_id', (int) Area::withoutGlobalScopes()->whereKey($model->area_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof SupportTicket && $model->customer_id) {
                $model->setAttribute('tenant_id', (int) Customer::withoutGlobalScopes()->whereKey($model->customer_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof SupportTicketMessage && $model->support_ticket_id) {
                $model->setAttribute('tenant_id', (int) SupportTicket::withoutGlobalScopes()->whereKey($model->support_ticket_id)->value('tenant_id'));

                return;
            }

            if ($model instanceof SupportTicketUpload && $model->support_ticket_id) {
                $model->setAttribute('tenant_id', (int) SupportTicket::withoutGlobalScopes()->whereKey($model->support_ticket_id)->value('tenant_id'));

                return;
            }

            if (auth('web')->check() && auth('web')->user()->tenant_id !== null) {
                $model->setAttribute('tenant_id', (int) auth('web')->user()->tenant_id);

                return;
            }

            $model->setAttribute('tenant_id', (int) (Tenant::query()->value('id') ?? 1));
        });
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
