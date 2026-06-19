<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Support\SupportSlaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToTenant;

    public const CHANNELS = [
        'web' => 'Web',
        'portal' => 'Customer portal',
        'live_chat' => 'Live chat',
        'app' => 'Mobile app',
        'whatsapp' => 'WhatsApp',
        'call_center' => 'Call center',
    ];

    public const ISSUE_TYPES = [
        'billing' => 'Billing / invoice',
        'connection' => 'Connection / no internet',
        'speed' => 'Slow speed',
        'outage' => 'Area outage',
        'installation' => 'Installation / new line',
        'equipment' => 'Equipment / ONU / router',
        'pppoe' => 'PPPoE / login issue',
        'fiber' => 'Fiber / optical',
        'wifi' => 'Wi-Fi / LAN',
        'relocation' => 'Relocation / shifting',
        'other' => 'Other',
    ];

    public const DEPARTMENTS = [
        'billing' => 'Billing',
        'technical_support' => 'Technical support',
        'field_engineer' => 'Field engineer',
        'network' => 'Network team',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'pending_customer' => 'Pending customer',
        'pending_vendor' => 'Pending vendor',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /** @deprecated Use pending_customer */
    public const LEGACY_PENDING = 'pending';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'parent_ticket_id',
        'root_incident_id',
        'ticket_number',
        'channel',
        'department',
        'priority',
        'status',
        'issue_type',
        'subject',
        'description',
        'assigned_to',
        'olt_device_id',
        'pop_box_id',
        'sla_resolve_due_at',
        'first_response_due_at',
        'first_responded_at',
        'first_response_breached_notified_at',
        'eta_at',
        'merged_at',
        'sla_profile',
        'resolved_at',
        'closed_at',
        'customer_rating',
        'customer_rating_comment',
        'escalation_level',
        'escalated_at',
        'sla_breached_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_resolve_due_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'first_response_breached_notified_at' => 'datetime',
            'eta_at' => 'datetime',
            'merged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalated_at' => 'datetime',
            'sla_breached_notified_at' => 'datetime',
            'escalation_level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if (blank($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateTicketNumber((int) $ticket->tenant_id);
            }

            if ($ticket->customer_id !== null && $ticket->relationLoaded('customer') === false) {
                $ticket->load('customer');
            }

            app(SupportSlaService::class)->applyTargets($ticket, $ticket->customer);
        });
    }

    public static function generateTicketNumber(int $tenantId): string
    {
        $prefix = (string) config('support.ticket_number_prefix', 'ISP').'-'.now()->format('Y').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('ticket_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function childTickets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ticket_id');
    }

    public function rootIncident(): BelongsTo
    {
        return $this->belongsTo(SupportRootIncident::class, 'root_incident_id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'olt_device_id');
    }

    public function popBox(): BelongsTo
    {
        return $this->belongsTo(PopBox::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function publicMessagesForCustomer(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class)->orderBy('scheduled_at');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(SupportTicketUpload::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed'], true);
    }

    public function isMergedChild(): bool
    {
        return $this->parent_ticket_id !== null
            && (int) $this->parent_ticket_id !== (int) $this->id;
    }

    public function isSlaBreached(): bool
    {
        return $this->isOpen()
            && $this->sla_resolve_due_at !== null
            && $this->sla_resolve_due_at->isPast();
    }

    public function slaRemainingLabel(): string
    {
        if ($this->sla_resolve_due_at === null || ! $this->isOpen()) {
            return '—';
        }

        if ($this->isSlaBreached()) {
            return 'Overdue '.$this->sla_resolve_due_at->diffForHumans(now(), true);
        }

        return $this->sla_resolve_due_at->diffForHumans(now(), true).' left';
    }

    public function etaLabel(): string
    {
        if ($this->eta_at === null || ! $this->isOpen()) {
            return '—';
        }

        if ($this->eta_at->isPast()) {
            return 'ETA passed '.$this->eta_at->diffForHumans(now(), true).' ago';
        }

        return $this->eta_at->format('M j, g:i A').' ('.$this->eta_at->diffForHumans().')';
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? (string) $this->channel;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }
}
