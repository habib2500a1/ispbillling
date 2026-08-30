<x-app-layout>
    <x-slot name="header">
        {{ __('Collections') }}
    </x-slot>
    <div class="row">
        <div class="col">
            @isset($fundFlow)
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">{{ __('Period total') }}</div>
                                <div class="fs-4 fw-bold text-success" id="cr-total">৳{{ number_format($fundFlow['total'] ?? 0, 2) }}</div>
                                <div class="small text-muted" id="cr-range">{{ $fundFlow['from'] ?? '' }} → {{ $fundFlow['to'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">{{ __('Collections count') }}</div>
                                <div class="fs-4 fw-bold" id="cr-count">{{ number_format($fundFlow['count'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">{{ __('Average ticket') }}</div>
                                <div class="fs-4 fw-bold" id="cr-avg">৳{{ number_format($fundFlow['avg'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endisset
            <div class="card">
                <div class="row p-3">
                    <div class="col">
                        <form class="row g-3 align-items-end" id="collection-report-form">
                            <div class="col-auto">
                                <label class="form-label small mb-0" for="from-date">{{ __('From') }}</label>
                                <input type="date" name="from-date" class="form-control" id="from-date" value="{{ $from }}">
                            </div>
                            <div class="col-auto">
                                <label class="form-label small mb-0" for="to-date">{{ __('To') }}</label>
                                <input type="date" name="to-date" class="form-control" id="to-date" value="{{ $to }}">
                            </div>
                            <div class="col-auto">
                                <label class="form-label small mb-0" for="collector">{{ __('Collector') }}</label>
                                <select name="collector" id="collector" class="form-control">
                                    <option value="">{{ __('All collectors') }}</option>
                                    @foreach ($collectors as $collector)
                                        <option value="{{ $collector->email }}">{{ $collector->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" id="report-submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered" id="collection-report-table">
                        <thead>
                            <tr>
                                <th>{{ __('Id') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Address') }}</th>
                                <th>{{ __('IP/Username') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                                <th>{{ __('Collected By') }}</th>
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
                        autoWidth: true,
                        responsive: true,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
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
                            { data: 'customers_address', name: 'customers_address', orderable: false, searchable: false },
                            { data: 'ppp_secret', name: 'ppp_secret', orderable: false, searchable: false },
                            { data: 'collection_amount', name: 'collection_amount', className: 'text-end' },
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
