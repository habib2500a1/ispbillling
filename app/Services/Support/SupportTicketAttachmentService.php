<?php

namespace App\Services\Support;

use App\Models\SupportTicketMessage;
use App\Models\SupportTicketMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class SupportTicketAttachmentService
{
    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'video/mp4', 'video/quicktime', 'video/webm',
    ];

    public const MAX_SIZE_KB = 10240;

    /**
     * @param  list<string>|string|null  $paths  Stored paths from Filament FileUpload or manual store
     */
    public function attachPathsToMessage(SupportTicketMessage $message, array|string|null $paths, string $disk = 'public'): void
    {
        if ($paths === null) {
            return;
        }

        foreach ((array) $paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
            $size = Storage::disk($disk)->size($path) ?: 0;
            $original = basename($path);

            SupportTicketMessageAttachment::query()->create([
                'tenant_id' => $message->tenant_id,
                'support_ticket_message_id' => $message->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $original,
                'mime' => $mime,
                'size' => $size,
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function attachUploadsToMessage(SupportTicketMessage $message, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store(
                'ticket-messages/'.$message->tenant_id.'/'.$message->support_ticket_id,
                'public',
            );

            SupportTicketMessageAttachment::query()->create([
                'tenant_id' => $message->tenant_id,
                'support_ticket_message_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }

    public function storeUpload(UploadedFile $file, int $tenantId, int $ticketId): string
    {
        return $file->store('ticket-messages/'.$tenantId.'/'.$ticketId, 'public');
    }
}
