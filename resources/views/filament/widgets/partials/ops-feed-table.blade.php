@php
    $rows = $rows ?? collect();
@endphp
<section class="isp-cmd-feed">
    <header class="isp-cmd-feed__head">
        <h3>{{ $title }}</h3>
    </header>
    <div class="isp-cmd-feed__body">
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
                        @foreach ($row as $cell)
                            <td>
                                @if (! empty($cell['url']))
                                    <a href="{{ $cell['url'] }}" class="isp-cmd-feed__link">{{ $cell['text'] }}</a>
                                @else
                                    {{ $cell['text'] }}
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
</section>
