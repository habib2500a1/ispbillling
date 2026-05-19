@props([
    'logs',
    'stats' => ['total' => 0, 'sent' => 0, 'failed' => 0],
    'eventLabels' => [],
    'fullLogUrl' => null,
])

<div class="isp-cd-sms-summary mb-3">
    <span><strong>{{ $stats['total'] }}</strong> total SMS</span>
    <span class="text-emerald-700"><strong>{{ $stats['sent'] }}</strong> sent</span>
    <span class="text-rose-700"><strong>{{ $stats['failed'] }}</strong> failed</span>
    @if ($fullLogUrl)
        <a href="{{ $fullLogUrl }}" class="isp-cd-sms-summary__link">Full log (admin) →</a>
    @endif
</div>

@if ($logs->isEmpty())
    <p class="text-sm text-gray-500">এই ক্লায়েন্টের কোনো SMS লগ নেই। বিল রিমাইন্ডার, পেমেন্ট লিংক বা OTP পাঠালে এখানে দেখা যাবে।</p>
@else
    <div class="isp-optical-power-wrap overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="isp-optical-power-table min-w-full isp-cd-sms-table">
            <thead>
                <tr>
                    <th>Date / time</th>
                    <th>Event</th>
                    <th>To</th>
                    <th>Status</th>
                    <th>DLR</th>
                    <th>Message</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    @php
                        $dlr = $log->smsDeliveryReport;
                        $eventLabel = $eventLabels[$log->event] ?? $log->event;
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap text-xs">
                            {{ $log->sent_at?->format('d-M-Y H:i') ?? $log->created_at?->format('d-M-Y H:i') }}
                        </td>
                        <td class="text-xs">{{ $eventLabel }}</td>
                        <td class="font-mono text-xs">{{ $log->recipient }}</td>
                        <td>
                            <span class="isp-cd-badge isp-cd-badge--{{ \App\Models\NotificationLog::statusColor($log->status) }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="text-xs">
                            @if ($dlr)
                                {{ ucfirst((string) $dlr->delivery_status) }}
                                @if ($dlr->status_text)
                                    <span class="text-gray-500">({{ $dlr->status_text }})</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="max-w-xs text-xs" title="{{ $log->message }}">
                            {{ \Illuminate\Support\Str::limit($log->message ?? '—', 80) }}
                        </td>
                        <td class="max-w-[10rem] text-xs text-rose-600" title="{{ $log->error }}">
                            {{ $log->error ? \Illuminate\Support\Str::limit($log->error, 40) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($stats['total'] > $logs->count())
        <p class="mt-2 text-xs text-gray-500">Showing latest {{ $logs->count() }} of {{ $stats['total'] }}. Use “Full log” for complete history.</p>
    @endif
@endif
