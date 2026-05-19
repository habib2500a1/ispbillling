<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerDocument extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'photo' => 'Profile photo',
        'nid_front' => 'NID (front)',
        'nid_back' => 'NID (back)',
        'contract' => 'Service contract',
        'address_proof' => 'Address proof',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'document_type',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'notes',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CustomerDocument $document): void {
            if (filled($document->path)) {
                Storage::disk($document->disk)->delete($document->path);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }
}
