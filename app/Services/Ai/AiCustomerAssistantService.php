<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Portal\CustomerBandwidthService;
use App\Services\Portal\CustomerOnuOpticalService;
use App\Support\SafeCache;

final class AiCustomerAssistantService
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiRagService $rag,
        private readonly AiAuditLogger $audit,
        private readonly CustomerOnuOpticalService $onu,
        private readonly CustomerBandwidthService $bandwidth,
    ) {}

    /**
     * @return array{reply: string, hints: list<string>, locale: string, advisory: bool}
     */
    public function reply(Customer $customer, string $question): array
    {
        $start = microtime(true);
        $tenantId = (int) $customer->tenant_id;
        abort_unless($this->settings->customerAiEnabled($tenantId), 503, 'Customer AI is disabled.');

        $q = mb_strtolower(trim($question));
        $bn = $this->settings->bengaliReplies($tenantId) && preg_match('/[\x{0980}-\x{09FF}]/u', $question);
        $locale = $bn ? 'bn' : 'en';
        $hints = [];

        $customer->loadMissing('package');
        $onu = SafeCache::remember('mobile_ai:onu:'.$customer->id, now()->addSeconds(45), fn (): array => $this->onu->snapshot($customer));
        $live = SafeCache::remember('mobile_ai:bandwidth:'.$customer->id, now()->addSeconds(30), fn (): array => $this->bandwidth->liveStats($customer));

        $reply = null;
        $tool = 'customer.general';

        if ($this->matches($q, ['ticket', 'টিকেট', 'complaint', 'অভিযোগ', 'status'])) {
            $tool = 'customer.tickets';
            $reply = $this->ticketStatusReply($customer, $bn);
            $hints[] = $bn ? 'সাপোর্ট → নতুন টিকেট' : 'Support → New ticket';
        } elseif ($this->matches($q, ['slow', 'speed', 'ধীর', 'গতি'])) {
            $tool = 'customer.speed';
            $hints[] = $bn ? 'Usage ট্যাবে লাইভ স্পিড দেখুন' : 'Check live usage in Usage tab';
            if (! ($live['online'] ?? false)) {
                $reply = $bn
                    ? 'আপনার কানেকশন অফলাইন দেখাচ্ছে। ONU/রাউটার চালু আছে কিনা দেখুন অথবা সাপোর্ট টিকেট খুলুন।'
                    : 'Your connection appears offline. Check ONU/router power or open a support ticket.';
            } else {
                $down = $live['download_human'] ?? '—';
                $reply = $bn
                    ? "আপনি অনলাইন আছেন। বর্তমান ডাউনলোড প্রায় {$down}। ধীর লাগলে রাউটার রিবুট করুন বা সাপোর্টে যোগাযোগ করুন।"
                    : "You are online. Current download is about {$down}. If it feels slow, reboot the router or contact support.";
            }
        } elseif ($this->matches($q, ['onu', 'signal', 'red', 'fiber', 'ফাইবার', 'সিগন্যাল', 'লাল'])) {
            $tool = 'customer.onu';
            if (! ($onu['linked'] ?? false)) {
                $reply = $bn
                    ? 'আপনার অ্যাকাউন্টে এখনো ONU লিংক নেই। EPON পোর্ট তথ্য দিয়ে সাপোর্ট টিকেট খুলুন।'
                    : 'No ONU is linked to your account yet. Open a support ticket with EPON port details.';
            } else {
                $rx = $onu['rx_dbm'] ?? 'unknown';
                $label = $onu['rx_level_label'] ?? 'Unknown';
                $reply = $bn
                    ? "ONU সিগন্যাল: RX {$rx} dBm ({$label}). ".(in_array($label, ['Critical', 'Warning'], true) ? 'দুর্বল সিগন্যাল — ভিজিটের জন্য টিমকে জানানো হবে।' : 'সিগন্যাল গ্রহণযোগ্য।')
                    : "ONU signal: RX {$rx} dBm ({$label}). ".(in_array($label, ['Critical', 'Warning'], true) ? 'Weak signal — we can schedule a visit.' : 'Signal looks acceptable.');
            }
            $hints[] = $bn ? 'Usage → ONU বিস্তারিত' : 'Usage → ONU details';
        } elseif ($this->matches($q, ['bill', 'pay', 'due', 'বিল', 'পেমেন্ট', 'বকেয়া', 'টাকা'])) {
            $tool = 'customer.billing';
            $due = (float) $customer->openInvoiceBalance();
            $reply = $bn
                ? 'আপনার খোলা বকেয়া '.number_format($due, 2).' BDT। Bills ট্যাব থেকে পেমেন্ট করুন।'
                : 'Your open balance is '.number_format($due, 2).' BDT. Pay from the Bills tab.';
            $hints[] = $bn ? 'Bills → Pay' : 'Bills → Pay';
        } elseif ($this->matches($q, ['package', 'upgrade', 'প্যাকেজ', 'আপগ্রেড'])) {
            $tool = 'customer.package';
            $pkg = $customer->package?->name ?? ($bn ? 'নির্ধারিত নয়' : 'not set');
            $reply = $bn
                ? "আপনার বর্তমান প্যাকেজ: {$pkg}। পরিবর্তনের জন্য Packages মেনু থেকে অনুরোধ করুন।"
                : "Your current package: {$pkg}. Request a change from the Packages section.";
        }

        if ($reply === null) {
            $rag = $this->rag->contextBlock($question, $tenantId);
            if ($rag !== '') {
                $tool = 'customer.rag';
                $reply = $bn
                    ? "নীতিমালা/নির্দেশনা:\n".$rag
                    : "Policy / guidance:\n".$rag;
            }
        }

        if ($reply === null) {
            $reply = $bn
                ? 'আমি ধীর ইন্টারনেট, ONU সিগন্যাল, বিল ও টিকেট স্ট্যাটাসে সাহায্য করতে পারি। যেমন: "আমার বিল কত?" বা "ইন্টারনেট ধীর কেন?"'
                : 'I can help with slow internet, ONU signal, billing, and tickets. Try: "What is my bill due?" or "Why is my internet slow?"';
            $hints[] = $bn ? 'সাপোর্ট → নতুন টিকেট' : 'Support → New ticket';
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        $this->audit->log('customer', $question, $reply, $tool, 'customer', false, $latency, $customer);

        return [
            'reply' => $reply,
            'hints' => $hints,
            'locale' => $locale,
            'advisory' => true,
        ];
    }

    private function ticketStatusReply(Customer $customer, bool $bn): string
    {
        $ticket = SupportTicket::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['closed'])
            ->latest('id')
            ->first();

        if ($ticket === null) {
            return $bn ? 'কোনো খোলা টিকেট নেই। প্রয়োজনে সাপোর্ট থেকে নতুন টিকেট খুলুন।' : 'No open tickets. Create one from Support if needed.';
        }

        return $bn
            ? "সর্বশেষ টিকেট #{$ticket->ticket_number} — স্ট্যাটাস: {$ticket->status}।"
            : "Latest ticket #{$ticket->ticket_number} — status: {$ticket->status}.";
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
