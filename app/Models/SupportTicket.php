<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
        'billing' => 'Billing',
        'connection' => 'Connection / no internet',
        'speed' => 'Slow speed',
        'outage' => 'Area outage',
        'installation' => 'Installation',
        'equipment' => 'Equipment / ONU',
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
        'in_progress' => 'In progress',
        'pending' => 'Pending',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'ticket_number',
        'channel',
        'department',
        'priority',
        'status',
        'issue_type',
        'subject',
        'description',
        'assigned_to',
        'sla_resolve_due_at',
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
            if ($ticket->sla_resolve_due_at === null && filled($ticket->priority)) {
                $hours = (int) (config('support.sla_resolve_hours.'.$ticket->priority) ?? 48);
                $ticket->sla_resolve_due_at = now()->addHours($hours);
            }
        });
    }

    public static function generateTicketNumber(int $tenantId): string
    {
        $prefix = 'TKT-'.now()->format('ym').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('ticket_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
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

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? (string) $this->channel;
    }
}
