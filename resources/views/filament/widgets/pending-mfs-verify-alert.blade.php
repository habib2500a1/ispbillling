@php
    $count = (int) ($count ?? 0);
@endphp

<x-filament-widgets::widget>
    @if ($count > 0)
        <div class="isp-mfs-pending-alert isp-mfs-pending-alert--widget" role="alert">
            <div class="isp-mfs-pending-alert__main">
                <strong>{{ $count }} টি bKash/Nagad পেমেন্ট যাচাই বাকি</strong>
                <p>ভুল TrxID বা SMS মিলেনি — ক্লায়েন্ট merchant নম্বরে কল করতে পারে; আপনি এখান থেকে Approve করুন।</p>
                <ul class="isp-mfs-pending-alert__list">
                    @foreach ($items as $row)
                        <li>
                            <span>{{ $row['gateway'] }}</span>
                            <code>{{ $row['trx'] }}</code>
                            <span>{{ $row['amount'] }} BDT</span>
                            <span>{{ $row['customer'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if (! empty($url))
                <a href="{{ $url }}" class="isp-mfs-pending-alert__cta">Pending payments →</a>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
