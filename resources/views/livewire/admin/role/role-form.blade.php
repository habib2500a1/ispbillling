<x-dialog-modal wire:model.live="confirmingRole" maxWidth="2xl" class="mt-2">
    <x-slot name="title">
        {{ $roleType }}
    </x-slot>

    <x-slot name="content">
        <form wire:submit.prevent="saveRole" method="post">
            <x-mikrotik.form-input
                labelClass="col-md-4 col-form-label text-md-end text-start"
                groupClass="col-md-7"
                label="{{ __('Role Name') }}"
                type="text"
                name="name"
                required="true"
            />

            <div class="mb-3 row">
                <label class="col-md-4 col-form-label text-md-end text-start fw-semibold text-dark">{{ __('Permissions') }} <span class="text-danger">*</span></label>
                <div class="col-md-7">
                    <div class="mb-2">
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm rounded-3 ps-3 pe-5" id="permissionSearch" placeholder="Search permissions..." onkeyup="filterPermissions()">
                            <i class="bi bi-search position-absolute end-0 top-50 translate-middle-y me-3 text-muted"></i>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 bg-white shadow-inner" style="max-height: 350px; overflow-y: auto;">
                        @foreach ($groupedPermissions ?? [] as $category => $items)
                            <div class="permission-section mb-3">
                                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 0.8px;">{{ $category }}</h6>
                                <div class="row g-2">
                                    @foreach ($items as $permission)
                                        <div class="col-sm-6 permission-card" data-name="{{ $permission['name'] }}">
                                            <label
                                                class="card h-100 border rounded-3 p-2 cursor-pointer transition-all position-relative overflow-hidden border-light bg-light bg-opacity-50"
                                                style="user-select: none; transition: all 0.15s ease-in-out;"
                                                x-data="{ pid: {{ $permission['id'] }}, checked: @js(in_array($permission['id'], $permissions ?? [])) }"
                                                :class="checked ? 'border-success bg-success-subtle bg-opacity-25' : 'border-light bg-light bg-opacity-50'"
                                                @click.prevent="checked = !checked; $refs.cb.checked = checked; $refs.cb.dispatchEvent(new Event('change', { bubbles: true }))"
                                            >
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                        <span class="d-inline-flex align-items-center justify-content-center rounded-2 p-1.5 bg-secondary bg-opacity-10 text-secondary" style="width: 28px; height: 28px;"
                                                              :class="checked ? 'bg-success text-white' : 'bg-secondary bg-opacity-10 text-secondary'">
                                                            <i class="bi" :class="checked ? 'bi-shield-check' : 'bi-shield'" style="font-size: 0.9rem;"></i>
                                                        </span>
                                                        <span class="fw-semibold text-truncate text-dark" style="font-size: 0.78rem;">{{ $permission['label'] }}</span>
                                                    </div>
                                                    <input x-ref="cb" class="form-check-input" type="checkbox" value="{{ $permission['id'] }}" wire:model.defer="permissions" style="display: none;">
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-error name="permissions" />
                    <div wire:loading wire:target="saveRole" class="small text-muted mt-2">
                        <i class="bi bi-arrow-repeat spin"></i> {{ __('Saving permissions…') }}
                    </div>
                </div>
            </div>

            <script>
                function filterPermissions() {
                    let input = document.getElementById('permissionSearch').value.toLowerCase();
                    document.querySelectorAll('.permission-card').forEach(card => {
                        let name = card.getAttribute('data-name').toLowerCase();
                        card.style.setProperty('display', name.includes(input) ? 'block' : 'none', 'important');
                    });
                }
            </script>

            <div class="row">
                <x-button-success type="submit" wire:loading.attr="disabled" wire:target="saveRole" class="col-md-3 offset-md-5">
                    <span wire:loading.remove wire:target="saveRole">{{ __('Save') }}</span>
                    <span wire:loading wire:target="saveRole">{{ __('Saving…') }}</span>
                </x-button-success>
            </div>
        </form>
    </x-slot>

    <x-slot name="footer">
        <x-button-danger wire:click="$toggle('confirmingRole')" wire:loading.attr="disabled">
            {{ __('Cancel') }}
        </x-button-danger>
    </x-slot>
</x-dialog-modal>
