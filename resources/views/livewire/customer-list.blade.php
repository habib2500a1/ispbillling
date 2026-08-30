<div class="cl-desk">
    <div class="cl-toolbar">
        <div>
            <h1 class="cl-title">{{ __('Clients') }}</h1>
            <p class="cl-sub mb-0">{{ __('Subscriber desk') }}</p>
        </div>
        <div class="cl-toolbar-actions">
            @if ($routers->count() > 1)
                <select class="form-select form-select-sm cl-select" id="router_filter" title="{{ __('Router') }}">
                    <option value="">{{ __('All Routers') }}</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->router_name }}">{{ $router->router_name }}</option>
                    @endforeach
                </select>
            @else
                <select class="d-none" id="router_filter"><option value=""></option></select>
            @endif
            @if(hasAccess(['Super Admin'], ['push-customers']))
                <button type="button" onclick="confirmPushAllCustomers()" class="btn btn-sm cl-btn-ghost" title="{{ __('Push all customers to MikroTik') }}">
                    <i class="bi bi-cloud-arrow-up"></i> <span class="d-none d-sm-inline">{{ __('Push All') }}</span>
                </button>
            @endif
            <button type="button" id="reset_table" class="btn btn-sm cl-btn-ghost" title="{{ __('Reset filters') }}">
                <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">{{ __('Reset') }}</span>
            </button>
            <a href="{{ route('customers.excel-upload') }}" class="btn btn-sm cl-btn-ghost">
                <i class="bi bi-file-earmark-excel"></i> <span class="d-none d-sm-inline">{{ __('Excel upload') }}</span>
            </a>
            <a href="{{ route('new-customer') }}" class="btn btn-sm cl-btn-ink">
                <i class="bi bi-person-plus"></i> <span class="d-none d-sm-inline">{{ __('New Client') }}</span>
            </a>
        </div>
    </div>

    <div class="cl-filter-card">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="filter-group cl-chips">
                            <input type="radio" class="btn-check" name="collection" id="all_list" autocomplete="off">
                            <label class="cl-chip" for="all_list">{{ __('All') }} <span class="cl-count"></span></label>
                            
                            <input type="radio" class="btn-check" name="collection" id="all_active_list" autocomplete="off" checked>
                            <label class="cl-chip" for="all_active_list">{{ __('Active') }} <span class="cl-count"></span></label>

                            <input type="radio" class="btn-check" name="collection" id="collection_list" autocomplete="off">
                            <label class="cl-chip" for="collection_list">{{ __('Paid') }} <span class="cl-count"></span></label>

                            <input type="radio" class="btn-check" name="collection" id="without_collection_list" autocomplete="off">
                            <label class="cl-chip" for="without_collection_list">{{ __('Unpaid') }} <span class="cl-count"></span></label>

                            <input type="radio" class="btn-check" name="collection" id="pending_customer" autocomplete="off">
                            <label class="cl-chip" for="pending_customer">{{ __('Pending') }} <span class="cl-count"></span></label>

                            <input type="radio" class="btn-check" name="collection" id="disable_customer" autocomplete="off">
                            <label class="cl-chip" for="disable_customer">{{ __('Disabled') }} <span class="cl-count"></span></label>

                            <div class="cl-more" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                                <button class="cl-chip cl-more-btn" type="button" @click.stop="open = !open" :class="{ 'is-on': open }" :aria-expanded="open">
                                    {{ __('More') }} <i class="bi small" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                </button>
                                <div class="cl-more-menu" x-show="open" x-cloak @click="open = false">
                                    <label class="dropdown-item" for="active_customer"><input type="radio" class="btn-check" name="collection" id="active_customer" autocomplete="off"> {{ __('Status: Active') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="free_customer"><input type="radio" class="btn-check" name="collection" id="free_customer" autocomplete="off"> {{ __('Free') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="vip_customer"><input type="radio" class="btn-check" name="collection" id="vip_customer" autocomplete="off"> {{ __('VIP') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="corporate_customer"><input type="radio" class="btn-check" name="collection" id="corporate_customer" autocomplete="off"> {{ __('Corporate') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="inactive_customer"><input type="radio" class="btn-check" name="collection" id="inactive_customer" autocomplete="off"> {{ __('Inactive') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="expired_customer"><input type="radio" class="btn-check" name="collection" id="expired_customer" autocomplete="off"> {{ __('Expired') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="expired_today_customer"><input type="radio" class="btn-check" name="collection" id="expired_today_customer" autocomplete="off"> {{ __('Expired Today') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="joined_today_customer"><input type="radio" class="btn-check" name="collection" id="joined_today_customer" autocomplete="off"> {{ __('Joined Today') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="joined_month_customer"><input type="radio" class="btn-check" name="collection" id="joined_month_customer" autocomplete="off"> {{ __('This Month') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="online_customer"><input type="radio" class="btn-check" name="collection" id="online_customer" autocomplete="off"> {{ __('Online') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="offline_customer"><input type="radio" class="btn-check" name="collection" id="offline_customer" autocomplete="off"> {{ __('Offline') }} <span class="cl-count"></span></label>
                                    <label class="dropdown-item" for="inactive_due_customer"><input type="radio" class="btn-check" name="collection" id="inactive_due_customer" autocomplete="off"> {{ __('Inactive Due') }} <span class="cl-count"></span></label>
                                    @foreach($resellers as $reseller)
                                        <label class="dropdown-item" for="reseller_{{ $reseller->id }}">
                                            <input type="radio" class="btn-check" name="collection" id="reseller_{{ $reseller->id }}" data-reseller-id="{{ $reseller->id }}" autocomplete="off">
                                            {{ $reseller->company ?: ($reseller->user->name ?? 'Reseller') }} <span class="cl-count"></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
    </div>

    <div class="cl-table-card">
        <div class="table-responsive bg-white px-3 pb-3" wire:ignore.self wire:key="customer-list-table-wrap">
            <table class="customer-table table table-hover custom-data-table border-0 w-100" style="width:100%"
                data-url="{{ route('customers.data') }}">
                <thead class="bg-light">
                    <tr>
                        <th class="text-center">{{ __('SL') }}</th>
                        <th class="text-center">{{ __('Customer Identity') }}</th>
                        <th class="text-center">{{ __('Address') }}</th>
                        <th class="text-center">{{ __('Billing Breakdown') }}</th>
                        <th class="text-center">{{ __('Connection Info') }}</th>
                        <th class="text-center">{{ __('Billing Summary') }}</th>
                        <th class="text-center">{{ __('Auto Disable') }}</th>
                        <th class="text-center">{{ __('Action') }}</th>
                        {{-- Raw columns for export (Indices 8-22) --}}
                        <th class="text-center">{{ __('ID') }}</th>
                        <th class="text-center">{{ __('Name') }}</th>
                        <th class="text-center">{{ __('Address') }}</th>
                        <th class="text-center">{{ __('Mobile') }}</th>
                        <th class="text-center">{{ __('IP') }}</th>
                        <th class="text-center">{{ __('Router') }}</th>
                        <th class="text-center">{{ __('Rent') }}</th>
                        <th class="text-center">{{ __('P.Due') }}</th>
                        <th class="text-center">{{ __('Add.') }}</th>
                        <th class="text-center">{{ __('Vat') }}</th>
                        <th class="text-center">{{ __('Disc') }}</th>
                        <th class="text-center">{{ __('Adv') }}</th>
                        <th class="text-center">{{ __('Bill') }}</th>
                        <th class="text-center">{{ __('Paid') }}</th>
                        <th class="text-center">{{ __('Due') }}</th>
                    </tr>
                </thead>
                <tbody class="text-center"></tbody>
                <tfoot class="bg-light border-top">
                    <tr class="page-totals-row">
                        @for($i=0; $i<23; $i++)
                            <th id="page_total_{{ $i }}" @if($i==0) class="text-end fw-bold" @elseif($i==3) class="text-start small" @elseif($i==5) class="text-end" @endif>
                                @if($i==0) {{ __('Page Totals:') }} @endif
                            </th>
                        @endfor
                    </tr>
                    <tr class="grand-totals-row">
                        @for($i=0; $i<23; $i++)
                            <th id="full_total_{{ $i }}" @if($i==0) class="text-end fw-bold" @elseif($i==3) class="text-start small" @elseif($i==5) class="text-end" @endif>
                                @if($i==0) {{ __('Grand Totals:') }} @endif
                            </th>
                        @endfor
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($editingCustomerId)
        <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog" wire:key="edit-customer-modal-{{ $editingCustomerId }}">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Edit Customer') }}</h5>
                        <button type="button" class="btn-close" wire:click="closeEditCustomerModal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body bg-light" style="padding: 1.5rem; max-height: 85vh; overflow-y: auto;">
                        @livewire('edit-customer', ['customerId' => $editingCustomerId], key($editingCustomerId))
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" wire:click="closeEditCustomerModal">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($editingBillId)
        <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog" wire:key="edit-bill-modal-{{ $editingBillId }}">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">{{ __('Update Bill') }} (<span>{{ $bill_customer_name }}</span>)</h1>
                        <button type="button" class="btn-close close" wire:click="closeBillModal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <form wire:submit.prevent="updateBill">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-5">
                                    {{ __('Unique ID:') }} <span>{{ $bill_customer_unique_id }}</span>
                                    <br>
                                    {{ __('Customer Name:') }} <span>{{ $bill_customer_name }}</span>
                                    <br>
                                    {{ __('IP/Username:') }} <span>{{ $bill_username }}</span>
                                    <br>
                                    {{ __('Auto Disable Date:') }} <span>{{ $bill_auto_disable_date ? \Carbon\Carbon::parse($bill_auto_disable_date)->format('d-M-y') : '' }}</span>
                                    <br>
                                    <br>
                                    <br>
                                    <br>
                                </div>
                                <div class="col-7 border-start">
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Monthly Rent :') }}</span>
                                        <input type="number" min="0" step="any" wire:model.live="monthly_rent" class="form-control" required>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Additional Charge :') }}</span>
                                        <input type="number" min="0" step="any" wire:model.live="additional_charge" class="form-control" required>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Vat (%) :') }}</span>
                                        <input type="number" min="0" step="any" wire:model.live="vat" class="form-control" required>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Sub Total :') }}</span>
                                        <input type="text" class="form-control" wire:model="sub_total_amount" disabled readonly>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Previous Due :') }}</span>
                                        <input type="number" step="any" wire:model.live="previous_due" class="form-control" disabled readonly>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Advance :') }}</span>
                                        <input type="number" min="0" step="any" wire:model.live="advance" class="form-control" required>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Discount :') }}</span>
                                        <input type="number" min="0" step="any" wire:model.live="discount" class="form-control" required>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Grand Total :') }}</span>
                                        <input type="text" wire:model="total_amount" class="form-control" disabled readonly>
                                        <span class="input-group-text">{{ __('Tk') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text ps-5 w-50">{{ __('Auto Disable :') }}</span>
                                        <div class="input-group-text form-check form-switch form-check-reverse w-50 text-center justify-content-center">
                                            <input class="form-check-input ms-0" wire:model="auto_disable" type="checkbox">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer pb-0 mb-0 border-top-0 pe-0">
                                <button type="button" class="btn btn-danger close" wire:click="closeBillModal">{{ __('Close') }}</button>
                                <button type="submit" class="btn btn-success">{{ __('Save Changes') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
    <style>
        .cl-desk {
            --cl-ink: #1e3a5f;
            --cl-line: #d7dee6;
            --cl-wash: #f4f6f8;
            padding: 0.75rem 0.75rem 1.25rem;
        }
        .cl-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .cl-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--cl-ink);
            margin: 0;
            letter-spacing: -0.02em;
        }
        .cl-sub { font-size: 0.78rem; color: #6b7580; }
        .cl-toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
        .cl-select { max-width: 160px; border-color: var(--cl-line); color: var(--cl-ink); }
        .cl-btn-ghost {
            border: 1px solid var(--cl-line);
            background: #fff;
            color: var(--cl-ink);
        }
        .cl-btn-ink { background: var(--cl-ink); color: #fff; border-color: var(--cl-ink); }
        .cl-btn-ink:hover { color: #fff; filter: brightness(1.08); }
        .cl-filter-card, .cl-table-card {
            background: #fff;
            border: 1px solid var(--cl-line);
            border-radius: 10px;
        }
        .cl-filter-card { padding: 0.65rem 0.75rem; margin-bottom: 0.75rem; overflow: visible; position: relative; z-index: 4; }
        .cl-table-card { overflow: hidden; }
        .cl-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; overflow: visible; padding-bottom: 2px; }
        .cl-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin: 0;
            padding: 0.28rem 0.7rem;
            border: 1px solid var(--cl-line);
            border-radius: 6px;
            background: #fff;
            color: var(--cl-ink);
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }
        .cl-chip:hover { background: var(--cl-wash); }
        .btn-check:checked + .cl-chip,
        .cl-more-menu .dropdown-item:has(.btn-check:checked) {
            background: var(--cl-ink);
            border-color: var(--cl-ink);
            color: #fff;
        }
        .cl-count:empty { display: none !important; }
        .cl-count {
            min-width: 1.25rem;
            padding: 0 0.35rem;
            border-radius: 4px;
            background: rgba(30, 58, 95, 0.1);
            font-variant-numeric: tabular-nums;
            font-size: 0.72rem;
        }
        .btn-check:checked + .cl-chip .cl-count { background: rgba(255,255,255,0.18); color: #fff; }
        [x-cloak] { display: none !important; }
        .cl-more { position: relative; }
        .cl-more-btn { border-style: dashed; }
        .cl-more-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            z-index: 1080;
            min-width: 16rem;
            max-height: 22rem;
            overflow: auto;
            padding: 0.4rem 0;
            background: #fff;
            border: 1px solid var(--cl-line);
            border-radius: 8px;
            box-shadow: 0 10px 28px rgba(30, 58, 95, 0.16);
        }
        .cl-more-menu .dropdown-item { font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; }
        .custom-data-table {
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            font-size: 0.82rem !important;
        }
        .custom-data-table thead th {
            text-transform: uppercase;
            font-weight: 650;
            letter-spacing: 0.03em;
            color: var(--cl-ink);
            background: var(--cl-wash) !important;
            border-bottom: 1px solid var(--cl-line) !important;
            padding: 10px 10px !important;
            white-space: nowrap;
        }
        .custom-data-table tbody tr {
            background-color: #fff !important;
        }
        .custom-data-table tbody tr:hover {
            background-color: #f7f9fb !important;
        }
        .custom-data-table td {
            padding: 10px 10px !important;
            vertical-align: middle !important;
            border-top: 1px solid #eef1f4 !important;
        }
        .badge-soft {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .action-btns .btn {
            width: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            margin: 0 2px !important;
            transition: all 0.2s !important;
        }
        .action-btns .btn:hover { filter: brightness(0.96); }
        .fw-600 { font-weight: 600; }
        .billing-card {
            min-width: 110px;
            padding: 4px 0;
        }
        .cl-chips::-webkit-scrollbar { height: 3px; }
        .cl-chips::-webkit-scrollbar-thumb { background: #cfd6de; border-radius: 10px; }
        .cl-more-btn.is-on { background: var(--cl-ink); color: #fff; border-style: solid; }
        .page-totals-row th, .grand-totals-row th {
            background: var(--cl-wash) !important;
            color: var(--cl-ink) !important;
            border-color: var(--cl-line) !important;
        }
        .dt-container .dt-search input {
            border: 1px solid var(--cl-line);
            background: #fff;
            border-radius: 6px;
            padding: 6px 12px;
        }
        .dt-container .dt-paging .dt-paging-button.current {
            background: #1e3a5f !important;
            color: #fff !important;
            border: 0;
            border-radius: 6px;
        }
        @media (max-width: 767.98px) {
            .cl-desk { padding: 0.5rem 0.4rem 1rem; }
            .cl-title { font-size: 1.05rem; }
            .cl-table-card .table-responsive { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            .action-btns { flex-wrap: wrap; }
        }
        .dt-container .dt-buttons {
            display: flex !important;
            flex-wrap: wrap !important;
            overflow: visible !important;
            gap: 6px !important;
            max-width: 100% !important;
            padding-bottom: 6px !important;
        }
        .dt-buttons .btn,
        .dt-buttons .dt-button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            margin: 0 !important;
            padding: 0.35rem 0.75rem !important;
            min-height: 34px !important;
            min-width: auto !important;
            width: auto !important;
            height: auto !important;
            color: #1e3a5f !important;
            background: #fff !important;
            border: 1px solid #d5dde6 !important;
            white-space: nowrap !important;
            overflow: visible !important;
            visibility: visible !important;
        }
        .dt-buttons .btn span,
        .dt-buttons .dt-button span,
        .dt-buttons .btn i,
        .dt-buttons .dt-button i {
            display: inline !important;
            visibility: visible !important;
            color: inherit !important;
            font-size: inherit !important;
        }
        .dt-container .dt-buttons::-webkit-scrollbar {
            height: 4px;
        }
        .dt-container .dt-buttons::-webkit-scrollbar-thumb {
            background: #e9ecef;
            border-radius: 10px;
        }
        .dt-paging {
            margin-top: 10px !important;
        }
        .dt-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.initCustomerListTable = function () {
            var $table = $('.customer-table');
            if (!$table.length || typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
                return false;
            }

            if ($.fn.DataTable.isDataTable($table[0])) {
                $table.DataTable().destroy();
                $table.find('tbody').empty();
            }

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            var table = $table.DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                pagingType: 'full_numbers',
                pageLength: 10,
                lengthChange: true,
                searchable: true,
                searchDelay: 280,
                search: { return: false },
                language: {
                    search: '',
                    searchPlaceholder: '{{ __("Name, ID, mobile, username…") }}',
                    processing: '{{ __("Searching…") }}'
                },
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                dom: '<"d-flex flex-column flex-md-row justify-content-md-between align-items-center gap-2 mb-3"Bf>rt<"d-flex flex-column flex-md-row justify-content-md-between align-items-center gap-2 mt-3"ip>',
                buttons: [
                    {
                        extend: 'pageLength',
                        className: 'btn btn-sm btn-outline-secondary'
                    },
                    {
                        extend: 'excelHtml5',
                        text: '{{ __("Excel") }}',
                        className: 'btn btn-sm btn-outline-secondary',
                        exportOptions: { columns: [0, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22], footer: true }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '{{ __("PDF") }}',
                        className: 'btn btn-sm btn-outline-secondary',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        exportOptions: { columns: [0, 8, 9, 10, 11, 12, 3, 20, 21, 22], footer: true }
                    },
                    {
                        extend: 'print',
                        text: '{{ __("Print") }}',
                        className: 'btn btn-sm btn-outline-secondary',
                        exportOptions: { columns: [0, 8, 9, 10, 11, 12, 3, 20, 21, 22], footer: true },
                        customize: function (win) {
                            $(win.document.body).find('h1').css('text-align', 'center').text('{{ __("Customer Billing Report") }}');
                        }
                    },
                    {
                        extend: 'colvis',
                        text: '{{ __("Columns") }}',
                        className: 'btn btn-sm btn-outline-secondary'
                    }
                ],
                ajax: {
                    url: '/customers/data',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    error: function (xhr) {
                        console.error('Customer list load failed', xhr.status, xhr.responseText ? xhr.responseText.slice(0, 200) : '');
                    },
                    data: function(d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        var checkedRadio = $('input[name="collection"]:checked');
                        var checkedId = checkedRadio.attr('id');

                        if ($('#all_list').is(':checked')) {
                            d.filter = 'all';
                        } else if ($('#all_active_list').is(':checked')) {
                            d.filter = 'all_active';
                        } else if ($('#active_customer').is(':checked')) {
                            d.filter = 'active';
                        } else if ($('#without_collection_list').is(':checked')) {
                            d.filter = 'without_collection';
                        } else if ($('#collection_list').is(':checked')) {
                            d.filter = 'collection';
                        } else if ($('#pending_customer').is(':checked')) {
                            d.filter = 'pending';
                        }else if ($('#disable_customer').is(':checked')) {
                            d.filter = 'disable';
                        }else if ($('#free_customer').is(':checked')) {
                            d.filter = 'free';
                        }else if ($('#vip_customer').is(':checked')) {
                            d.filter = 'vip';
                        }else if ($('#corporate_customer').is(':checked')) {
                            d.filter = 'corporate';
                        }else if ($('#inactive_customer').is(':checked')) {
                            d.filter = 'inactive';
                        }else if ($('#expired_customer').is(':checked')) {
                            d.filter = 'expired';
                        }else if ($('#expired_today_customer').is(':checked')) {
                            d.filter = 'expired_today';
                        }else if ($('#joined_today_customer').is(':checked')) {
                            d.filter = 'joined_today';
                        }else if ($('#joined_month_customer').is(':checked')) {
                            d.filter = 'joined_month';
                        }else if ($('#online_customer').is(':checked')) {
                            d.filter = 'online';
                        }else if ($('#offline_customer').is(':checked')) {
                            d.filter = 'offline';
                        }else if ($('#inactive_due_customer').is(':checked')) {
                            d.filter = 'inactive_due';
                        }else if (checkedId && checkedId.startsWith('reseller_')) {
                            d.filter = 'reseller';
                            d.reseller_id = checkedRadio.data('reseller-id');
                        }
                        d.router_name = $('#router_filter').val();
                        if (!d.search) d.search = {};
                        if (!d.search.value) {
                            var typed = $('.dt-search input').val() || new URLSearchParams(window.location.search).get('q') || '';
                            if (typed) d.search.value = typed;
                        }
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', title: '{{ __('SL') }}', searchable: false, orderable: false, className: 'text-center' },
                    { data: 'customer_identity', name: 'customer_name', title: '{{ __('Customer Identity') }}', className: 'text-start' },
                    { data: 'customers_address', name: 'customers_address', title: '{{ __('Address') }}', className: 'text-start' },
                    { data: 'billing_breakdown', name: 'billing.monthly_rent', title: '{{ __('Billing Breakdown') }}', className: 'text-start' },
                    { data: 'connection_details', name: 'ppp_user.username', title: '{{ __('Connection Info') }}', className: 'text-start' },
                    { data: 'billing_summary', name: 'billing.total_amount', title: '{{ __('Billing Summary') }}', className: 'text-end' },
                    { data: 'disable_details', name: 'disable_details', title: '{{ __('Auto Disable') }}', className: 'text-center' },
                    { data: 'action', name: 'action', title: '{{ __('Action') }}', orderable: false, searchable: false, className: 'text-center' },
                    
                    // Invisible columns for raw data & totals (8-22)
                    { data: 'customer_unique_id', name: 'customer_unique_id', title: '{{ __('ID') }}', visible: false, searchable: false },
                    { data: 'customer_name', name: 'customer_name', title: '{{ __('Name') }}', visible: false, searchable: false },
                    { data: 'customers_address', name: 'customers_address', title: '{{ __('Address') }}', visible: false, searchable: false },
                    { data: 'mobile', name: 'mobile', title: '{{ __('Mobile') }}', visible: false, searchable: false },
                    { data: 'ppp_user.username', name: 'ppp_user.username', title: '{{ __('IP') }}', visible: false, searchable: false },
                    { data: 'ppp_user.router_name', name: 'ppp_user.router_name', title: '{{ __('Router') }}', visible: false, searchable: false },
                    { data: 'billing.monthly_rent', name: 'billing.monthly_rent', title: '{{ __('Rent') }}', visible: false, searchable: false },
                    { data: 'billing.previous_due', name: 'billing.previous_due', title: '{{ __('P.Due') }}', visible: false, searchable: false },
                    { data: 'billing.additional_charge', name: 'billing.additional_charge', title: '{{ __('Add.') }}', visible: false, searchable: false },
                    { data: 'billing.vat', name: 'billing.vat', title: '{{ __('Vat') }}', visible: false, searchable: false },
                    { data: 'billing.discount', name: 'billing.discount', title: '{{ __('Disc') }}', visible: false, searchable: false },
                    { data: 'billing.advance', name: 'billing.advance', title: '{{ __('Adv') }}', visible: false, searchable: false },
                    { data: 'billing.total_amount', name: 'billing.total_amount', title: '{{ __('Bill') }}', visible: false, searchable: false },
                    { data: 'billing.paid_amount', name: 'billing.paid_amount', title: '{{ __('Paid') }}', visible: false, searchable: false },
                    { data: 'billing.due_amount', name: 'billing.due_amount', title: '{{ __('Due') }}', visible: false, searchable: false }
                ],
                footerCallback: function () {
                    try {
                    var api = this.api();
                    var intVal = function (i) {
                        if (typeof i === 'string') return i.replace(/[\$,]/g, '') * 1;
                        if (typeof i === 'number') return i;
                        return 0;
                    };

                    var sumColumn = function (colIdx, page, clampZero) {
                        var data = api.column(colIdx, page ? { page: 'current' } : undefined).data().toArray();
                        return data.reduce(function (a, b) {
                            var n = intVal(b);
                            return intVal(a) + (clampZero ? Math.max(0, n) : n);
                        }, 0);
                    };

                    var fields = {
                        rent: 14, prev_due: 15, add_charge: 16, vat: 17, disc: 18, adv: 19,
                        bill: 20, paid: 21, due: 22
                    };

                    var pageTotals = {}, grandTotals = {};

                    Object.keys(fields).forEach(function(key) {
                        var colIdx = fields[key];
                        var clampZero = key === 'due';
                        pageTotals[key] = sumColumn(colIdx, true, clampZero);
                        grandTotals[key] = sumColumn(colIdx, false, clampZero);
                    });

                    // Update UI safely (Matching Table Column Design)
                    var breakdownStyle = 'style="font-size: 0.7rem; line-height: 1.4;"';
                    
                    if ($('#page_total_3').length) {
                        $('#page_total_3').html(
                            '<div class="text-muted" ' + breakdownStyle + '>' +
                            '<div><i class="bi bi-calendar3 me-1"></i>{{ __("Rent:") }} <span class="text-dark fw-bold">' + pageTotals.rent.toFixed(2) + '</span></div>' +
                            '<div><i class="bi bi-exclamation-triangle me-1"></i>{{ __("P.Due:") }} <span class="text-dark fw-bold">' + pageTotals.prev_due.toFixed(2) + '</span></div>' +
                            '<div><i class="bi bi-plus-circle me-1"></i>{{ __("Add:") }} <span class="text-dark fw-bold">' + pageTotals.add_charge.toFixed(2) + '</span> | <i class="bi bi-percent me-1"></i>{{ __("Vat:") }} <span class="text-dark fw-bold">' + pageTotals.vat.toFixed(0) + '</span></div>' +
                            '<div><i class="bi bi-tag me-1"></i>{{ __("Disc:") }} <span class="text-danger fw-bold">' + pageTotals.disc.toFixed(2) + '</span> | <i class="bi bi-wallet-fill me-1"></i>{{ __("Adv:") }} <span class="text-success fw-bold">' + pageTotals.adv.toFixed(2) + '</span></div>' +
                            '</div>'
                        );
                    }
 
                    if ($('#page_total_5').length) {
                        $('#page_total_5').html(
                            '<div class="billing-card small shadow-none border-0 text-start bg-transparent p-0">' +
                            '<div class="d-flex justify-content-between text-muted"><span>{{ __("Bill:") }}</span> <span class="fw-bold text-primary">' + pageTotals.bill.toFixed(2) + '</span></div>' +
                            '<div class="d-flex justify-content-between text-muted"><span>{{ __("Paid:") }}</span> <span class="fw-bold text-success">' + pageTotals.paid.toFixed(2) + '</span></div>' +
                            '<hr class="my-1">' +
                            '<div class="d-flex justify-content-between"><span>{{ __("Due:") }}</span> <span class="fw-bold text-danger">' + pageTotals.due.toFixed(2) + '</span></div>' +
                            '</div>'
                        );
                    }
 
                    if ($('#full_total_3').length) {
                        $('#full_total_3').html(
                            '<div class="text-muted" ' + breakdownStyle + '>' +
                            '<div><i class="bi bi-calendar3 me-1 text-primary"></i>{{ __("Rent:") }} <span class="text-primary fw-bold">' + grandTotals.rent.toFixed(2) + '</span></div>' +
                            '<div><i class="bi bi-exclamation-triangle me-1 text-primary"></i>{{ __("P.Due:") }} <span class="text-primary fw-bold">' + grandTotals.prev_due.toFixed(2) + '</span></div>' +
                            '<div><i class="bi bi-plus-circle me-1 text-primary"></i>{{ __("Add:") }} <span class="text-primary fw-bold">' + grandTotals.add_charge.toFixed(2) + '</span> | <i class="bi bi-percent me-1 text-primary"></i>{{ __("Vat:") }} <span class="text-primary fw-bold">' + grandTotals.vat.toFixed(0) + '</span></div>' +
                            '<div><i class="bi bi-tag me-1 text-primary"></i>{{ __("Disc:") }} <span class="text-primary fw-bold">' + grandTotals.disc.toFixed(2) + '</span> | <i class="bi bi-wallet-fill me-1 text-primary"></i>{{ __("Adv:") }} <span class="text-primary fw-bold">' + grandTotals.adv.toFixed(2) + '</span></div>' +
                            '</div>'
                        );
                    }
 
                    if ($('#full_total_5').length) {
                        $('#full_total_5').html(
                            '<div class="billing-card small shadow-none border-0 text-start bg-transparent p-0">' +
                            '<div class="d-flex justify-content-between text-primary"><span>{{ __("Bill:") }}</span> <span class="fw-bold">' + grandTotals.bill.toFixed(2) + '</span></div>' +
                            '<div class="d-flex justify-content-between text-primary"><span>{{ __("Paid:") }}</span> <span class="fw-bold">' + grandTotals.paid.toFixed(2) + '</span></div>' +
                            '<hr class="my-1 border-primary opacity-50">' +
                            '<div class="d-flex justify-content-between text-primary"><span>{{ __("Due:") }}</span> <span class="fw-bold">' + grandTotals.due.toFixed(2) + '</span></div>' +
                            '</div>'
                        );
                    }

                    // Populate raw cells for Export/Print clarity
                    $('#page_total_20').text(pageTotals.bill.toFixed(2));
                    $('#page_total_21').text(pageTotals.paid.toFixed(2));
                    $('#page_total_22').text(pageTotals.due.toFixed(2));

                    $('#full_total_20').text(grandTotals.bill.toFixed(2));
                    $('#full_total_21').text(grandTotals.paid.toFixed(2));
                    $('#full_total_22').text(grandTotals.due.toFixed(2));
                    } catch (e) {
                        console.error('Customer list footer totals failed', e);
                    }
                }
            });

            window.customerListTable = table;
            var urlQ = new URLSearchParams(window.location.search).get('q');
            if (urlQ) {
                $('.dt-search input').val(urlQ);
                table.search(urlQ);
            }
            if (window.innerWidth < 768) {
                table.columns([0, 2, 3, 6]).visible(false);
            }

            function updateCustomerCount() {
                var count = table.page.info().recordsTotal;
                $('.cl-count').text('');
                var checkedId = $('input[name="collection"]:checked').attr('id');
                if (checkedId) {
                    $('label[for="' + checkedId + '"] .cl-count').text(count);
                }
                var inMore = $('#cl-more-menu .btn-check:checked').length > 0 || $('.cl-more-menu .btn-check:checked').length > 0;
                $('.cl-more-btn').toggleClass('is-on', inMore);
            }

            table.on('draw', function () { updateCustomerCount(); });
            
            // Logic to reset table search when a filter is clicked
            function resetTableState() {
                table.search('').columns().search('');
                $('.dt-search input').val('');
            }

            $('input[name="collection"]').off('change.customerList').on('change.customerList', function() { 
                resetTableState();
                table.clear().draw(); 
                table.ajax.reload(null, true); 
            });

            $('#router_filter').off('change.customerList').on('change.customerList', function() { 
                resetTableState();
                table.clear().draw();
                table.ajax.reload(null, true); 
            });

            $('#reset_table').off('click.customerList').on('click.customerList', function() {
                $('#router_filter').val('');
                $('#all_active_list').prop('checked', true);
                resetTableState();
                table.clear().draw();
                table.ajax.reload(null, true);
            });

            window.confirmDeleteCustomer = function(encryptedId) {
                Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: "{{ __('You won\'t be able to revert this!') }}",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "{{ __('Yes, delete it!') }}"
                }).then((result) => {
                    if (result.isConfirmed) { Livewire.dispatch('delete-customer', { id: encryptedId }); }
                });
            };
 
            window.confirmEnableCustomer = function(encryptedId) {
                Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: "{{ __('Enable this customer?') }}",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "{{ __('Yes, Enable it!') }}"
                }).then((result) => {
                    if (result.isConfirmed) { Livewire.dispatch('enable-customer', { id: encryptedId }); }
                });
            };

            window.confirmPushCustomer = function(encryptedId) {
                Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: "{{ __('Push this customer configuration to MikroTik?') }}",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#f0ad4e",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "{{ __('Yes, Push it!') }}"
                }).then((result) => {
                    if (result.isConfirmed) { Livewire.dispatch('push-customer', { id: encryptedId }); }
                });
            };

            window.confirmPushAllCustomers = function() {
                Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: "{{ __('Push configuration for ALL customers to MikroTik?') }}",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#f0ad4e",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "{{ __('Yes, Push All!') }}"
                }).then((result) => {
                    if (result.isConfirmed) { Livewire.dispatch('push-all-customers'); }
                });
            };

            Livewire.on('customer-action-done', () => { table.ajax.reload(null, false); });

            return true;
        };

        let bootAttempts = 0;
        function bootCustomerListTable() {
            if (window.initCustomerListTable()) {
                bootAttempts = 0;
                return;
            }
            if (++bootAttempts < 30) {
                setTimeout(bootCustomerListTable, 200);
            }
        }

        bootCustomerListTable();
        document.addEventListener('livewire:navigated', bootCustomerListTable);

        // Dashboard deep-link: /customers?filter=expired
        (function applyUrlFilter() {
            const params = new URLSearchParams(window.location.search);
            const f = params.get('filter');
            if (!f) return;
            const map = {
                all: '#all_list',
                all_active: '#all_active_list',
                active: '#active_customer',
                pending: '#pending_customer',
                disable: '#disable_customer',
                inactive: '#inactive_customer',
                expired: '#expired_customer',
                expired_today: '#expired_today_customer',
                joined_today: '#joined_today_customer',
                joined_month: '#joined_month_customer',
                online: '#online_customer',
                offline: '#offline_customer',
                inactive_due: '#inactive_due_customer',
                free: '#free_customer',
            };
            const sel = map[f];
            if (sel && document.querySelector(sel)) {
                document.querySelector(sel).checked = true;
                setTimeout(function () {
                    if (window.customerListTable) {
                        window.customerListTable.ajax.reload();
                    } else {
                        $(sel).trigger('change');
                    }
                }, 600);
            }
        })();
    </script>
@endpush

<script>
    (function () {
        function runCustomerListBoot() {
            if (document.querySelector('.customer-table') && typeof window.initCustomerListTable === 'function') {
                setTimeout(window.initCustomerListTable, 250);
            }
        }
        runCustomerListBoot();
        document.addEventListener('livewire:navigated', runCustomerListBoot);
    })();
</script>