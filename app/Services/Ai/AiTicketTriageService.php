<?php

namespace App\Services\Ai;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Str;

final class AiTicketTriageService
{
    /**
     * @return array<string, mixed>
     */
    public function triage(string $subject, string $description = ''): array
    {
        $text = mb_strtolower($subject.' '.$description);

        $issueType = match (true) {
            str_contains($text, 'bill') || str_contains($text, 'বিল') || str_contains($text, 'invoice') => 'billing',
            str_contains($text, 'slow') || str_contains($text, 'ধীর') || str_contains($text, 'speed') => 'speed',
            str_contains($text, 'outage') || str_contains($text, 'এলাকা') || str_contains($text, 'area down') => 'outage',
            str_contains($text, 'onu') || str_contains($text, 'fiber') || str_contains($text, 'optical') || str_contains($text, 'ফাইবার') => 'fiber',
            str_contains($text, 'wifi') || str_contains($text, 'ওয়াইফাই') => 'wifi',
            str_contains($text, 'install') || str_contains($text, 'নতুন') || str_contains($text, 'connection') => 'installation',
            str_contains($text, 'login') || str_contains($text, 'pppoe') => 'pppoe',
            default => 'other',
        };

        $priority = match (true) {
            str_contains($text, 'urgent') || str_contains($text, 'জরুরি') || str_contains($text, 'no internet') || str_contains($text, 'ইন্টারনেট নেই') => 'critical',
            str_contains($text, 'outage') || str_contains($text, 'area') => 'high',
            $issueType === 'billing' => 'medium',
            default => 'medium',
        };

        $department = match ($issueType) {
            'billing' => 'billing',
            'installation' => 'field_engineer',
            'fiber', 'outage', 'pppoe', 'speed', 'wifi' => 'technical_support',
            default => 'technical_support',
        };

        return [
            'issue_type' => $issueType,
            'priority' => $priority,
            'department' => $department,
            'suggested_subject' => Str::limit(trim($subject), 120),
            'suggested_reply_bn' => $this->suggestedReplyBn($issueType),
            'suggested_reply_en' => $this->suggestedReplyEn($issueType),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function triageTicket(SupportTicket $ticket): array
    {
        $result = $this->triage((string) $ticket->subject, (string) $ticket->description);
        $assignee = $this->suggestAssignee($ticket->tenant_id, $result['department']);

        return array_merge($result, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'suggested_assignee' => $assignee,
        ]);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function suggestAssignee(int $tenantId, string $department): ?array
    {
        $role = match ($department) {
            'billing' => ['cashier', 'isp-manager'],
            'field_engineer' => ['technician', 'field-engineer'],
            default => ['technician', 'noc', 'isp-manager'],
        };

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $role))
            ->orderBy('id')
            ->first();

        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    private function suggestedReplyBn(string $issueType): string
    {
        return match ($issueType) {
            'billing' => 'আপনার বিল সংক্রান্ত সমস্যা পেয়েছি। আমরা শীঘ্রই যাচাই করে জানাব।',
            'speed' => 'ধীর গতির অভিযোগ পেয়েছি। লাইন চেক করে আপডেট দেব।',
            'outage' => 'এলাকায় আউটেজ রিপোর্ট পেয়েছি। NOC টিম যাচাই করছে।',
            'fiber' => 'ONU/ফাইবার সমস্যা নোট করা হয়েছে। টেকনিশিয়ান পাঠানো হবে।',
            default => 'আপনার টিকেট পেয়েছি। শীঘ্রই আপডেট দেব।',
        };
    }

    private function suggestedReplyEn(string $issueType): string
    {
        return match ($issueType) {
            'billing' => 'We received your billing issue and will verify shortly.',
            'speed' => 'We logged your slow speed report and will check the line.',
            'outage' => 'Area outage reported — NOC is investigating.',
            'fiber' => 'ONU/fiber issue noted — a technician will be assigned.',
            default => 'Ticket received — we will update you soon.',
        };
    }
}
