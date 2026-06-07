@php
    $feedItems = [];

    if (($mfsPending['count'] ?? 0) > 0) {
        $feedItems['mfs'] = [
            'label' => 'MFS pending',
            'title' => 'MFS pending verify (wrong TrxID / SMS)',
            'columns' => ['Gateway', 'TrxID', 'BDT', 'Subscriber', 'When'],
            'rows' => collect($mfsPending['items'] ?? [])->map(fn ($r) => [
                ['text' => $r['gateway']],
                ['text' => $r['trx'], 'url' => $r['url'] ?? null],
                ['text' => $r['amount']],
                ['text' => $r['customer']],
                ['text' => $r['at']],
            ]),
        ];
    }

    $feedItems['payments'] = [
        'label' => 'Payments',
        'title' => 'Recent collections',
        'columns' => ['Gateway', 'TrxID', 'BDT', 'Subscriber', 'When'],
        'rows' => collect($feeds['recent_payments'] ?? [])->map(fn ($r) => [
            ['text' => $r['gateway']],
            ['text' => $r['trx']],
            ['text' => $r['amount']],
            ['text' => $r['customer'], 'url' => $r['url'] ?? null],
            ['text' => $r['at']],
        ]),
    ];

    if (($feeds['activity_log'] ?? []) !== []) {
        $feedItems['activity'] = [
            'label' => 'Activity',
            'title' => 'Recent activity',
            'columns' => ['Type', 'Summary', 'Detail', 'When'],
            'rows' => collect($feeds['activity_log'] ?? [])->map(fn ($r) => [
                ['text' => $r['type']],
                ['text' => $r['summary'], 'url' => $r['url'] ?? null],
                ['text' => $r['detail']],
                ['text' => $r['at']],
            ]),
        ];
    }

    $feedItems['customers'] = [
        'label' => 'Customers',
        'title' => 'Recent customers',
        'columns' => ['Subscriber', 'Package BDT', 'Joined'],
        'rows' => collect($feeds['recent_customers'] ?? [])->map(fn ($r) => [
            ['text' => $r['user'], 'url' => $r['url'] ?? null],
            ['text' => $r['bill']],
            ['text' => $r['joined']],
        ]),
    ];

    $feedItems['invoices'] = [
        'label' => 'Invoices',
        'title' => 'Recent invoices',
        'columns' => ['Invoice', 'Subscriber', 'BDT'],
        'rows' => collect($feeds['invoices'] ?? [])->map(fn ($r) => [
            ['text' => $r['no'], 'url' => $r['url']],
            ['text' => $r['user'], 'url' => $r['url']],
            ['text' => $r['amount']],
        ]),
    ];

    $feedItems['expiring'] = [
        'label' => 'Expiring',
        'title' => 'Expiring soon (7 days)',
        'columns' => ['Subscriber', 'Package BDT', 'Expires'],
        'rows' => collect($feeds['upcoming_expire'] ?? [])->map(fn ($r) => [
            ['text' => $r['user'], 'url' => $r['url']],
            ['text' => $r['bill']],
            ['text' => $r['expire']],
        ]),
    ];

    $feedItems['expired'] = [
        'label' => 'Expired',
        'title' => 'Recently expired',
        'columns' => ['Subscriber', 'Package BDT', 'Expired'],
        'rows' => collect($feeds['latest_expired'] ?? [])->map(fn ($r) => [
            ['text' => $r['user'], 'url' => $r['url']],
            ['text' => $r['bill']],
            ['text' => $r['expire']],
        ]),
    ];

    $feedItems['due'] = [
        'label' => 'Due',
        'title' => 'Top due balance',
        'columns' => ['Subscriber', 'Due BDT'],
        'rows' => collect($feeds['top_due'] ?? [])->map(fn ($r) => [
            ['text' => $r['user'], 'url' => $r['url']],
            ['text' => $r['due']],
        ]),
    ];

    $defaultFeed = array_key_first($feedItems) ?? 'payments';
@endphp

<div
    class="isp-cmd-feeds isp-cmd-feeds--v2"
    x-data="{ feedTab: @js($defaultFeed) }"
>
    <nav class="isp-cmd-feeds__tabs" aria-label="Activity feeds">
        @foreach ($feedItems as $key => $feed)
            <button
                type="button"
                class="isp-cmd-feeds__tab"
                :class="{ 'isp-cmd-feeds__tab--active': feedTab === @js($key) }"
                @click="feedTab = @js($key)"
            >
                {{ $feed['label'] }}
                <span class="isp-cmd-feeds__tab-count">{{ $feed['rows']->count() }}</span>
            </button>
        @endforeach
    </nav>

    <div class="isp-cmd-feeds__panels">
        @foreach ($feedItems as $key => $feed)
            <div x-show="feedTab === @js($key)" x-cloak>
                @include('filament.widgets.partials.ops-feed-table', [
                    'title' => $feed['title'],
                    'columns' => $feed['columns'],
                    'rows' => $feed['rows'],
                    'feedKey' => $key,
                ])
            </div>
        @endforeach
    </div>
</div>
