<x-app-layout>
    <x-slot name="header">
        {{ __('Collection Report') }}
    </x-slot>

    <style>
        .cr-page { --cr-navy:#1e3a5f; --cr-teal:#06ad73; }
        .cr-page .cr-card { border:0; border-radius:14px; box-shadow:0 10px 28px rgba(30,58,95,.07); }
        .cr-page .cr-stat { min-height:100%; }
        .cr-page .cr-stat .cr-value { font-size:clamp(1.25rem, 3vw, 1.6rem); font-weight:800; letter-spacing:-.02em; }
        .cr-page .form-label { font-weight:700; font-size:.8rem; color:var(--cr-navy); }
        .cr-page .form-control, .cr-page .form-select, .cr-page .btn-cr { min-height:44px; }
        .cr-page .btn-cr { background:var(--cr-teal); border-color:var(--cr-teal); font-weight:700; color:#fff; }
        .cr-page .table { margin-bottom:0; }
        .cr-page .table thead th { white-space:nowrap; font-size:.8rem; color:var(--cr-navy); background:#f8fafc; }
        .cr-page .table td { vertical-align:middle; font-size:.9rem; }
        .cr-page .cr-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .cr-page .dt-buttons { display:flex; flex-wrap:wrap; gap:.4rem; }
        .cr-page .dt-buttons .dt-button,
        .cr-page .dt-buttons .btn {
            background:#fff !important;
            color:var(--cr-navy) !important;
            border:1px solid #c5d0dc !important;
            box-shadow:none !important;
            min-height:36px !important;
            padding:.4rem .85rem !important;
            border-radius:8px !important;
            font-size:.8rem !important;
            font-weight:700 !important;
            line-height:1.2 !important;
        }
        .cr-page .dt-buttons .dt-button span,
        .cr-page .dt-buttons .btn span { color:inherit !important; visibility:visible !important; }
        .cr-page .dataTables_filter, .cr-page .dataTables_length { margin-bottom:.5rem; }
        .cr-page .dataTables_filter input { min-height:40px; }
        .cr-page .cr-dt-toolbar,
        .cr-page .cr-dt-foot { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
        @media (max-width: 767.98px) {
            .cr-page .cr-head { gap:.35rem; }
            .cr-page .cr-stat .cr-value { font-size:1.35rem; }
            .cr-page .cr-table-wrap { margin:0 -0.75rem; padding:0 .25rem; }
            .cr-page .dataTables_filter { width:100%; }
            .cr-page .dataTables_filter label,
            .cr-page .dataTables_filter input { width:100% !important; }
            .cr-page .dataTables_length { width:100%; }
            .cr-page .dataTables_info, .cr-page .dataTables_paginate { width:100%; }
            .cr-page .dataTables_paginate { overflow-x:auto; }
            .cr-page .table td, .cr-page .table th { white-space:nowrap; }
            .cr-page .cr-addr { max-width:180px; overflow:hidden; text-overflow:ellipsis; }
        }
        @media (min-width: 768px) {
            .cr-page .cr-filters .form-control,
            .cr-page .cr-filters .form-select { min-width:0; }
        }
    </style>

    <div class="cr-page px-0 px-md-1">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 cr-head">
            <div>
                <h4 class="mb-1 fw-bold" style="color:var(--cr-navy);">{{ __('Collection Report') }}</h4>
                <p class="text-muted small mb-0">
                    @if($canReviewAll ?? true)
                        {{ __('Filter by date and collector. Each collection stays on that staff or admin name.') }}
                    @else
                        {{ __('Your collections only. Amounts credit your staff account.') }}
                    @endif
                </p>
            </div>
        </div>

        @isset($fundFlow)
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card cr-card cr-stat">
                        <div class="card-body py-3">
                            <div class="text-muted small text-uppercase fw-bold">{{ __('Period total') }}</div>
                            <div class="cr-value text-success" id="cr-total">৳{{ number_format($fundFlow['total'] ?? 0, 2) }}</div>
                            <div class="small text-muted" id="cr-range">{{ $fundFlow['from'] ?? '' }} → {{ $fundFlow['to'] ?? '' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-6 col-xl-4">
                    <div class="card cr-card cr-stat">
                        <div class="card-body py-3">
                            <div class="text-muted small text-uppercase fw-bold">{{ __('Collections') }}</div>
                            <div class="cr-value" id="cr-count">{{ number_format($fundFlow['count'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-6 col-xl-4">
                    <div class="card cr-card cr-stat">
                        <div class="card-body py-3">
                            <div class="text-muted small text-uppercase fw-bold">{{ __('Average') }}</div>
                            <div class="cr-value" id="cr-avg">৳{{ number_format($fundFlow['avg'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endisset

        <div class="card cr-card">
            <div class="card-body p-3 p-md-4">
                <form class="row g-3 cr-filters align-items-end" id="collection-report-form">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label mb-1" for="from-date">{{ __('From') }}</label>
                        <input type="date" name="from-date" class="form-control" id="from-date" value="{{ $from }}">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label mb-1" for="to-date">{{ __('To') }}</label>
                        <input type="date" name="to-date" class="form-control" id="to-date" value="{{ $to }}">
                    </div>
                    <div class="col-12 col-sm-8 col-lg-4">
                        <label class="form-label mb-1" for="collector">{{ __('Collector') }}</label>
                        @if($canReviewAll ?? true)
                            <select name="collector" id="collector" class="form-select">
                                <option value="">{{ __('All collectors') }}</option>
                                @foreach ($collectors as $collector)
                                    <option value="{{ $collector->email }}">{{ $collector->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $viewerName }}" readonly>
                            <input type="hidden" name="collector" id="collector" value="{{ auth()->user()->email }}">
                        @endif
                    </div>
                    <div class="col-12 col-sm-4 col-lg-2">
                        <button type="submit" id="report-submit" class="btn btn-success btn-cr w-100">
                            <i class="bi bi-funnel me-1"></i>{{ __('Apply') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-body pt-0 px-3 px-md-4 pb-3 pb-md-4">
                <div class="cr-table-wrap">
                    <table class="table table-hover table-bordered align-middle nowrap w-100" id="collection-report-table">
                        <thead>
                            <tr>
                                <th>{{ __('Id') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Address') }}</th>
                                <th>{{ __('IP/Username') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                                <th>{{ __('Staff name') }}</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">{{ __('Total') }}</th>
                                <th class="text-end" id="cr-table-total">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                function money(n) {
                    return '৳' + Number(n || 0).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function applySummary(s) {
                    if (!s) return;
                    var totalEl = document.getElementById('cr-total');
                    var countEl = document.getElementById('cr-count');
                    var avgEl = document.getElementById('cr-avg');
                    var rangeEl = document.getElementById('cr-range');
                    var footEl = document.getElementById('cr-table-total');
                    if (totalEl) totalEl.textContent = money(s.total);
                    if (countEl) countEl.textContent = Number(s.count || 0).toLocaleString();
                    if (avgEl) avgEl.textContent = money(s.avg);
                    if (rangeEl) rangeEl.textContent = (s.from || '') + ' → ' + (s.to || '');
                    if (footEl) footEl.textContent = Number(s.total || 0).toFixed(2);
                }

                function isMobile() {
                    return window.matchMedia('(max-width: 767.98px)').matches;
                }

                function initCollectionReport() {
                    if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') return;
                    var $table = $('#collection-report-table');
                    if (!$table.length) return;

                    if ($.fn.DataTable.isDataTable($table)) {
                        $table.DataTable().ajax.reload();
                        return;
                    }

                    $table.DataTable({
                        processing: true,
                        serverSide: true,
                        autoWidth: false,
                        responsive: true,
                        scrollX: isMobile(),
                        pageLength: isMobile() ? 10 : 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                        dom: "<'cr-dt-toolbar'<'cr-dt-buttons'B><'cr-dt-search'f>>t<'cr-dt-foot'<'cr-dt-len'l><'cr-dt-info'i><'cr-dt-page'p>>",
                        buttons: [
                            { extend: 'copy', text: 'Copy' },
                            { extend: 'excel', text: 'Excel' },
                            { extend: 'pdf', text: 'PDF' },
                            { extend: 'print', text: 'Print' }
                        ],
                        columnDefs: [
                            { responsivePriority: 1, targets: [2, 5, 6] },
                            { responsivePriority: 2, targets: [1] },
                            { responsivePriority: 3, targets: [0, 4] },
                            { responsivePriority: 4, targets: 3 }
                        ],
                        ajax: {
                            url: "{{ route('collection-report.index') }}",
                            data: function (d) {
                                d.fromDate = $('#from-date').val();
                                d.toDate = $('#to-date').val();
                                d.collector = $('#collector').val();
                            },
                            dataSrc: function (json) {
                                applySummary(json.summary);
                                return json.data || [];
                            }
                        },
                        columns: [
                            { data: 'customer_collection_unique_id', name: 'customer_collection_unique_id' },
                            { data: 'collection_date', name: 'collection_date' },
                            { data: 'customer_name', name: 'customer.customer_name' },
                            { data: 'customers_address', name: 'customers_address', orderable: false, searchable: false, className: 'cr-addr' },
                            { data: 'ppp_secret', name: 'ppp_secret', orderable: false, searchable: false },
                            { data: 'collection_amount', name: 'collection_amount', className: 'text-end fw-semibold' },
                            { data: 'collected_by', name: 'collected_by' }
                        ]
                    });
                }

                function bindForm() {
                    $('#collection-report-form').off('submit.cr').on('submit.cr', function (e) {
                        e.preventDefault();
                        initCollectionReport();
                        if ($.fn.DataTable.isDataTable('#collection-report-table')) {
                            $('#collection-report-table').DataTable().ajax.reload();
                        }
                    });
                }

                function boot() {
                    bindForm();
                    initCollectionReport();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
                document.addEventListener('livewire:navigated', boot);
            })();
        </script>
    @endpush
</x-app-layout>
