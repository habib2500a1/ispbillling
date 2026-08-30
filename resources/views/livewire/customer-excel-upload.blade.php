<div>
    <x-slot name="header">
        {{ __('Upload users from Excel') }}
    </x-slot>

    <div class="card border-0 shadow-sm rounded-4 mb-3" style="border-left: 5px solid #1e3a5f !important;">
        <div class="card-body">
            <h5 class="fw-bold mb-1">{{ __('Excel user upload') }}</h5>
            <p class="small text-muted mb-0">
                {{ __('Super Admin, ISP admin, and staff with customer access can download the demo file, fill their users, then upload. Empty customer_id gets an auto ID. Users land in your own ISP only.') }}
            </p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold">1. {{ __('Download demo Excel') }}</h6>
                    <p class="small text-muted">{{ __('Open in Excel / Google Sheets. Keep the header row. Replace the two sample clients with real data.') }}</p>
                    <button type="button" class="btn btn-outline-success" wire:click="downloadDemo">
                        <i class="bi bi-download me-1"></i>{{ __('Download demo Excel') }}
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold">2. {{ __('Upload filled file') }}</h6>
                    <p class="small text-muted">{{ __('xlsx, xls, or csv. Same columns as the demo.') }}</p>
                    <input type="file" class="form-control mb-2" wire:model="file" accept=".xlsx,.xls,.csv">
                    @error('file') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="small text-muted mb-2" wire:loading wire:target="file">{{ __('Uploading…') }}</div>
                    <button type="button" class="btn btn-success" wire:click="import" wire:loading.attr="disabled" @disabled(! $file)>
                        <i class="bi bi-upload me-1"></i>{{ __('Upload users') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($result)
        <div class="card border-0 shadow-sm rounded-4 mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-2">{{ __('Last upload') }}</h6>
                <div class="d-flex flex-wrap gap-3 small mb-2">
                    <span>{{ __('Created') }}: <strong>{{ $result['created'] }}</strong></span>
                    <span>{{ __('Updated') }}: <strong>{{ $result['updated'] }}</strong></span>
                    <span>{{ __('Skipped') }}: <strong>{{ $result['skipped'] }}</strong></span>
                </div>
                @if(! empty($result['errors']))
                    <ul class="small text-danger mb-0">
                        @foreach(array_slice($result['errors'], 0, 20) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body">
            <h6 class="fw-bold mb-2">{{ __('Columns') }}</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Header') }}</th>
                            <th>{{ __('Required') }}</th>
                            <th>{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>customer_name</code></td><td>{{ __('Yes') }}</td><td>{{ __('Client full name') }}</td></tr>
                        <tr><td><code>mobile</code></td><td>{{ __('Recommended') }}</td><td>{{ __('01XXXXXXXXX — 88 is added if needed') }}</td></tr>
                        <tr><td><code>customer_id</code></td><td>{{ __('No') }}</td><td>{{ __('Leave empty for auto ID. Same ID updates that client.') }}</td></tr>
                        <tr><td><code>username</code> / <code>password</code></td><td>{{ __('No') }}</td><td>{{ __('Creates a PPPoE secret in this billing software') }}</td></tr>
                        <tr><td><code>package</code></td><td>{{ __('No') }}</td><td>{{ __('Must match a package name you already created') }}</td></tr>
                        <tr><td><code>monthly_rent</code></td><td>{{ __('No') }}</td><td>{{ __('Uses package price if empty') }}</td></tr>
                        <tr><td><code>billing_day</code></td><td>{{ __('No') }}</td><td>{{ __('1–28. Bill generate day.') }}</td></tr>
                        <tr><td><code>expire_date</code></td><td>{{ __('No') }}</td><td>{{ __('Y-m-d e.g. 2026-09-30') }}</td></tr>
                        <tr><td><code>due_amount</code></td><td>{{ __('No') }}</td><td>{{ __('Opening due. 0 if empty.') }}</td></tr>
                        <tr><td><code>status</code></td><td>{{ __('No') }}</td><td>active / disable / pending</td></tr>
                        <tr><td><code>address</code> · <code>email</code> · <code>notes</code></td><td>{{ __('No') }}</td><td></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('All Clients') }}</a>
                <a href="{{ route('new-customer') }}" class="btn btn-sm btn-outline-secondary">{{ __('Add one client') }}</a>
                <a href="{{ route('import.form') }}" class="btn btn-sm btn-outline-secondary">{{ __('Old MikroTik match import') }}</a>
            </div>
        </div>
    </div>
</div>
