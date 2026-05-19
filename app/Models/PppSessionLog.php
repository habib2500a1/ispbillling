<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PppSessionLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'mikrotik_server_id',
        'device_id',
        'session_key',
        'username',
        'framed_ip',
        'caller_id',
        'bytes_in',
        'bytes_out',
        'peak_rate_in_bps',
        'peak_rate_out_bps',
        'started_at',
        'ended_at',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'peak_rate_in_bps' => 'integer',
            'peak_rate_out_bps' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ended_at === null;
    }

    public function durationSeconds(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }

    public function formattedDuration(): string
    {
        $s = $this->durationSeconds();
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;

        return sprintf('%dd:%dh:%dm:%ds', $d, $h, $m, $sec);
    }

    public function liveDownloadBps(): ?int
    {
        $meta = $this->meta ?? [];
        if (isset($meta['rate_download_bps']) && $meta['rate_download_bps'] !== null) {
            return (int) $meta['rate_download_bps'];
        }

        return $this->peak_rate_in_bps > 0 ? (int) $this->peak_rate_in_bps : null;
    }

    public function liveUploadBps(): ?int
    {
        $meta = $this->meta ?? [];
        if (isset($meta['rate_upload_bps']) && $meta['rate_upload_bps'] !== null) {
            return (int) $meta['rate_upload_bps'];
        }

        return $this->peak_rate_out_bps > 0 ? (int) $this->peak_rate_out_bps : null;
    }
}
