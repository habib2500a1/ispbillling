@php
    $rows = $rows ?? collect();
    $feedKey = $feedKey ?? 'feed';
@endphp
<section class="isp-cmd-feed isp-cmd-feed--pro isp-cmd-feed--responsive" data-feed-key="{{ $feedKey }}">
    <header class="isp-cmd-feed__head">
        <h3>{{ $title }}</h3>
        <span class="isp-cmd-feed__count">{{ $rows->count() }}</span>
    </header>

    {{-- Mobile: card list (touch-friendly) --}}
    <div class="isp-cmd-feed__cards" aria-label="{{ $title }}">
        @forelse ($rows as $row)
            <article class="isp-cmd-feed-card">
                @foreach ($columns as $colIndex => $col)
                    @php $cell = $row[$colIndex] ?? ['text' => '—']; @endphp
                    <div @class([
                        'isp-cmd-feed-card__row',
                        'isp-cmd-feed-card__row--trx' => in_array($col, ['TrxID', 'Invoice'], true),
                    ])>
                        <span class="isp-cmd-feed-card__label">{{ $col }}</span>
                        <span class="isp-cmd-feed-card__value">
                            @if (! empty($cell['url']))
                                <a href="{{ $cell['url'] }}" class="isp-cmd-feed__link">{{ $cell['text'] }}</a>
                            @else
                                {{ $cell['text'] }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </article>
        @empty
            <p class="isp-cmd-feed__empty isp-cmd-feed__empty--block">কোনো রেকর্ড নেই</p>
        @endforelse
    </div>

    {{-- Desktop: scrollable table --}}
    <div class="isp-cmd-feed__body isp-cmd-feed__body--table">
        <div class="isp-cmd-feed__scroll">
            <table class="isp-cmd-feed__table">
                <thead>
                    <tr>
                        @foreach ($columns as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($row as $cellIndex => $cell)
                                <td @class([
                                    'isp-cmd-feed__td--mono' => ($columns[$cellIndex] ?? '') === 'TrxID',
                                    'isp-cmd-feed__td--truncate' => in_array($columns[$cellIndex] ?? '', ['TrxID', 'Invoice', 'Detail'], true),
                                ])>
                                    @if (! empty($cell['url']))
                                        <a href="{{ $cell['url'] }}" class="isp-cmd-feed__link" title="{{ $cell['text'] }}">{{ $cell['text'] }}</a>
                                    @else
                                        <span title="{{ $cell['text'] }}">{{ $cell['text'] }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="isp-cmd-feed__empty">কোনো রেকর্ড নেই</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
