<div class="zoom-in">
    <x-slot name="header">{{ __('Import PPP users') }} — {{ $routerName }}</x-slot>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('mikrotik-sync') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to Mikrotik List') }}
        </a>
        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="loadFromRouter" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="loadFromRouter"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Reload from router') }}</span>
            <span wire:loading wire:target="loadFromRouter" class="spinner-border spinner-border-sm"></span>
        </button>
        <span class="badge bg-primary">{{ $secretTotal }} {{ __('on router') }}</span>
    </div>

    @if ($loadError !== '')
        <div class="alert alert-danger">{{ $loadError }}</div>
    @endif

    @if ($lastMessage !== '')
        <div class="alert alert-{{ $lastMessageType === 'success' ? 'success' : ($lastMessageType === 'warning' ? 'warning' : 'danger') }}">
            {{ $lastMessage }}
            @if($lastMessageType === 'success')
                <a href="{{ route('customers.index') }}" class="alert-link ms-2">{{ __('Open customers') }}</a>
            @endif
        </div>
    @endif

    @if ($loadError === '')
        <div class="card border-0 shadow-sm rounded-4"
            wire:key="secrets-{{ $secretTotal }}"
            x-data="{
                q: '',
                page: 1,
                perPage: 40,
                all: {{ \Illuminate\Support\Js::from($secretList) }},
                selected: [],
                get filtered() {
                    const s = this.q.trim().toLowerCase();
                    if (!s) return this.all;
                    return this.all.filter(u =>
                        (u.name || '').toLowerCase().includes(s)
                        || (u.profile || '').toLowerCase().includes(s)
                        || (u.comment || '').toLowerCase().includes(s)
                    );
                },
                get pageCount() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
                get rows() {
                    const start = (this.page - 1) * this.perPage;
                    return this.filtered.slice(start, start + this.perPage);
                },
                toggle(name) {
                    if (this.selected.includes(name)) this.selected = this.selected.filter(n => n !== name);
                    else this.selected = [...this.selected, name];
                },
                selectPage() {
                    this.selected = [...new Set([...this.selected, ...this.rows.map(r => r.name)])];
                },
                clearSel() { this.selected = []; },
                doImportSelected() {
                    if (!this.selected.length) { alert(@js(__('Select at least one user'))); return; }
                    $wire.importNames([...this.selected]);
                },
                doImportAll() {
                    if (!confirm(@js(__('Import EVERY user from this router?')))) return;
                    $wire.importAllNames(this.all.map(u => u.name));
                }
            }"
            x-effect="q; page = 1">
            <div class="card-body">
                <div class="alert alert-info py-2 px-3 small">
                    {{ __('Type to search instantly. Row Import = 1 user. Or tick → Import selected.') }}
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <input type="search" class="form-control form-control-sm" style="max-width:280px"
                        x-model="q" placeholder="{{ __('Search username…') }}" autofocus>

                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="selectPage()">{{ __('Select page') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearSel()">{{ __('Clear') }}</button>

                    <span class="badge bg-secondary" x-text="filtered.length + ' match'"></span>
                    <span class="badge bg-success" x-text="selected.length + ' selected'"></span>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="createMissing" wire:model="createMissing">
                        <label class="form-check-label small" for="createMissing">{{ __('Create new') }}</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="updateExisting" wire:model="updateExisting">
                        <label class="form-check-label small" for="updateExisting">{{ __('Update existing') }}</label>
                    </div>
                    <select class="form-select form-select-sm" style="max-width:180px" wire:model="codeFormat">
                        <option value="prefix_sequential">{{ __('Prefix + sequence') }}</option>
                        <option value="secret_as_code">{{ __('PPP username = ID') }}</option>
                        <option value="numeric">{{ __('Numbers only') }}</option>
                    </select>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px"></th>
                                <th>{{ __('PPP User') }}</th>
                                <th>{{ __('Profile') }}</th>
                                <th>{{ __('Comment') }}</th>
                                <th class="text-end" style="width:110px">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="secret in rows" :key="secret.name">
                                <tr>
                                    <td>
                                        <input class="form-check-input" type="checkbox"
                                            :checked="selected.includes(secret.name)"
                                            @change="toggle(secret.name)">
                                    </td>
                                    <td class="fw-semibold">
                                        <span x-text="secret.name"></span>
                                        <span class="badge bg-warning text-dark" x-show="secret.disabled">{{ __('disabled') }}</span>
                                    </td>
                                    <td class="small" x-text="secret.profile"></td>
                                    <td class="small text-muted" x-text="(secret.comment || '').slice(0, 50)"></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary"
                                            @click="$wire.importOne(secret.name)">
                                            <i class="bi bi-download"></i> {{ __('Import') }}
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filtered.length === 0">
                                <td colspan="5" class="text-center text-muted py-4">{{ __('No users match search.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" @click="page = Math.max(1, page - 1)" :disabled="page <= 1">{{ __('Prev') }}</button>
                        <span class="btn btn-light disabled"><span x-text="page"></span> / <span x-text="pageCount"></span></span>
                        <button type="button" class="btn btn-outline-secondary" @click="page = Math.min(pageCount, page + 1)" :disabled="page >= pageCount">{{ __('Next') }}</button>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="doImportAll()">
                            {{ __('Import all (optional)') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-success" @click="doImportSelected()" :disabled="selected.length === 0">
                            <i class="bi bi-cloud-download me-1"></i>
                            {{ __('Import selected') }}
                            <span x-show="selected.length > 0">(<span x-text="selected.length"></span>)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
