<div class="zoom-in pb-5" x-data="{ 
    step: @entangle('step'),
    isLarge: window.innerWidth >= 992
}" x-init="window.addEventListener('resize', () => isLarge = window.innerWidth >= 992)">

    <x-slot name="header">
        <h2 class="h4 font-weight-bold text-dark mb-0">
            <i class="bi bi-person-plus me-2 text-success"></i>{{ __('New Customer Registration') }}
        </h2>
    </x-slot>

    <!-- Progress Steps Bar (Mobile Only - Horizontally Scrollable) -->
    <div class="card border-0 shadow-sm mb-4 d-lg-none" x-show="!isLarge" x-transition.opacity>
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between position-relative px-2 overflow-auto" style="scrollbar-width: none; -ms-overflow-style: none; gap: 15px;">
                <!-- Inner Container for Steps Line behind -->
                <div class="position-absolute bg-light" style="height: 4px; left: 10%; right: 10%; top: 38%; transform: translateY(-50%); z-index: 1;">
                    <div class="bg-success" style="height: 100%; transition: width 0.3s;"
                         :style="'width: ' + ((step - 1) * @if(!auth()->user()->hasRole('Reseller')) 25 @else 33.33 @endif) + '%'"></div>
                </div>

                <!-- Step 1: Personal Info -->
                <div class="d-flex flex-column align-items-center position-relative flex-shrink-0" style="z-index: 2; cursor: pointer; width: 60px;" @click="$wire.validateStepAndGo(1)">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-center" 
                         style="width: 40px; height: 40px; border: 2px solid; font-size: 0.9rem; transition: all 0.3s;"
                         :style="step >= 1 ? 'background-color: var(--cp-success); border-color: var(--cp-success); color: #fff; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);' : 'background-color: #fff; border-color: #dee2e6; color: #6c757d;'">
                        <span x-show="step <= 1">1</span>
                        <i x-show="step > 1" class="bi bi-check-lg fw-bold"></i>
                    </div>
                    <span class="small mt-1 text-center fw-bold" style="font-size: 0.7rem; white-space: nowrap;" :class="step === 1 ? 'text-success' : 'text-muted'">{{ __('Personal') }}</span>
                </div>

                <!-- Step 2: Address -->
                <div class="d-flex flex-column align-items-center position-relative flex-shrink-0" style="z-index: 2; cursor: pointer; width: 60px;" @click="$wire.validateStepAndGo(2)">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-center" 
                         style="width: 40px; height: 40px; border: 2px solid; font-size: 0.9rem; transition: all 0.3s;"
                         :style="step >= 2 ? 'background-color: var(--cp-success); border-color: var(--cp-success); color: #fff; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);' : 'background-color: #fff; border-color: #dee2e6; color: #6c757d;'">
                        <span x-show="step <= 2">2</span>
                        <i x-show="step > 2" class="bi bi-check-lg fw-bold"></i>
                    </div>
                    <span class="small mt-1 text-center fw-bold" style="font-size: 0.7rem; white-space: nowrap;" :class="step === 2 ? 'text-success' : 'text-muted'">{{ __('Address') }}</span>
                </div>

                <!-- Step 3: Server Info -->
                @if(!auth()->user()->hasRole('Reseller'))
                <div class="d-flex flex-column align-items-center position-relative flex-shrink-0" style="z-index: 2; cursor: pointer; width: 60px;" @click="$wire.validateStepAndGo(3)">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-center" 
                         style="width: 40px; height: 40px; border: 2px solid; font-size: 0.9rem; transition: all 0.3s;"
                         :style="step >= 3 ? 'background-color: var(--cp-success); border-color: var(--cp-success); color: #fff; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);' : 'background-color: #fff; border-color: #dee2e6; color: #6c757d;'">
                        <span x-show="step <= 3">3</span>
                        <i x-show="step > 3" class="bi bi-check-lg fw-bold"></i>
                    </div>
                    <span class="small mt-1 text-center fw-bold" style="font-size: 0.7rem; white-space: nowrap;" :class="step === 3 ? 'text-success' : 'text-muted'">{{ __('Network') }}</span>
                </div>
                @endif

                <!-- Step 4 / 3: Billing -->
                <div class="d-flex flex-column align-items-center position-relative flex-shrink-0" style="z-index: 2; cursor: pointer; width: 60px;" @click="$wire.validateStepAndGo(@if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif)">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-center" 
                         style="width: 40px; height: 40px; border: 2px solid; font-size: 0.9rem; transition: all 0.3s;"
                         :style="step >= @if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif ? 'background-color: var(--cp-success); border-color: var(--cp-success); color: #fff; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);' : 'background-color: #fff; border-color: #dee2e6; color: #6c757d;'">
                        <span x-show="step <= @if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif">@if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif</span>
                        <i x-show="step > @if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif" class="bi bi-check-lg fw-bold"></i>
                    </div>
                    <span class="small mt-1 text-center fw-bold" style="font-size: 0.7rem; white-space: nowrap;" :class="step === @if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif ? 'text-success' : 'text-muted'">{{ __('Billing') }}</span>
                </div>

                <!-- Step 5 / 4: Office Info -->
                <div class="d-flex flex-column align-items-center position-relative flex-shrink-0" style="z-index: 2; cursor: pointer; width: 60px;" @click="$wire.validateStepAndGo(@if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif)">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-center" 
                         style="width: 40px; height: 40px; border: 2px solid; font-size: 0.9rem; transition: all 0.3s;"
                         :style="step === @if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif ? 'background-color: var(--cp-success); border-color: var(--cp-success); color: #fff; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);' : 'background-color: #fff; border-color: #dee2e6; color: #6c757d;'">
                        <span>@if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif</span>
                    </div>
                    <span class="small mt-1 text-center fw-bold" style="font-size: 0.7rem; white-space: nowrap;" :class="step === @if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif ? 'text-success' : 'text-muted'">{{ __('Office') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Stack Form -->
    <form wire:submit.prevent="save">
        <div class="row g-3"> 
            <!-- Step 1: Customer Information -->
            <div class="col-12" x-show="isLarge || step === 1" x-transition.opacity>
                <x-mikrotik.section-form :class="'row'">
                    <x-slot name="title">
                        <span class="text-success fw-bold"><i class="bi bi-person me-2"></i>{{ __('Customer Information') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <x-mikrotik.form-group
                            label="{{ __('Customer Name') }}"
                            name="customer_name"
                            type="text"
                            placeholder="{{ __('Customer Name (eg. Mr. John Doe)') }}"
                            required="true"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Contact Person') }}"
                            name="contact_person"
                            type="text"
                            placeholder="{{ __('Optional — office / family contact') }}"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Father / Parents Name') }}"
                            name="parents_name"
                            type="text"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Spouse Name') }}"
                            name="spouse_name"
                            type="text"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Email Address') }}"
                            name="email"
                            type="text"
                            placeholder="johndoe@example.com"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('NID / Identification No') }}"
                            name="identification_no"
                            type="text"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Mobile Number') }}"
                            type="mobile"
                            name="mobile"
                            required="true"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Alternate Mobile Number') }}"
                            type="mobile"
                            name="alternative_mobile"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Profession Details') }}"
                            type="text"
                            name="profession"
                            placeholder="{{ __('eg. Software Engineer, Student, etc.') }}"
                        />
                        @if(!auth()->user()->hasRole('Reseller'))
                        <x-mikrotik.form-group
                            label="{{ __('Account Status') }}"
                            type="dropdownKey"
                            name="status"
                            placeholder="{{ __('Select Any One') }}"
                            :options="['active' => __('Active'), 'pending' => __('Pending'), 'disable' => __('Disable')]"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Customer ID (optional)') }}"
                            type="text"
                            name="customer_code"
                            placeholder="{{ __('Leave empty for auto ID') }}"
                        />
                        @endif
                        <x-mikrotik.form-group
                            label="{{ __('Activation / Connection Date') }}"
                            type="date"
                            name="connection_date"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Image') }}"
                            type="file"
                            name="photo_url"
                        />
                        @if ($photo_url)
                            <div class="mt-3 d-flex align-items-center gap-2">
                                <div>
                                    <label class="d-block text-muted small">{{ __('Photo Preview:') }}</label>
                                    <img src="{{ $photo_url->temporaryUrl() }}" alt="Image Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm px-2" style="border-radius: 6px;" wire:click="removePhoto">
                                    <i class="bi bi-trash-fill"></i> {{ __('Remove') }}
                                </button>
                            </div>
                        @endif
                    </x-slot>
                    <x-section-border/>
                </x-mikrotik.section-form>
            </div>

            <!-- Step 2: Customer Address -->
            <div class="col-12" x-show="isLarge || step === 2" x-transition.opacity>
                <x-mikrotik.section-form :class="'row'">
                    <x-slot name="title">
                        <span class="text-success fw-bold"><i class="bi bi-geo-alt me-2"></i>{{ __('Customer Address') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <x-mikrotik.form-group
                            label="{{ __('Full Address') }}"
                            type="text"
                            name="flat_address"
                            placeholder="{{ __('House, road, area (single line)') }}"
                        />
                        @foreach ($addressFields as $addressField)
                            <x-mikrotik.form-group
                                label="{{ __($addressField['label']) }}"
                                type="{{ $addressField['input_type'] }}"
                                name="address.{{$addressField['label']}}"
                                required="{{$addressField['required'] == 1 ? '*' : ''}}"
                                placeholder="{{$addressField['input_type'] == 'dropdown' ? __('Select Any One') : __($addressField['label'])}}"
                                :options="json_decode($addressField['dropdown_list'])"
                            />
                        @endforeach
                        <x-mikrotik.form-group
                            label="{{ __('GPS Latitude') }}"
                            type="text"
                            name="gps_lat"
                            placeholder="23.8103"
                            column="col-md-6 col-sm-6"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('GPS Longitude') }}"
                            type="text"
                            name="gps_lng"
                            placeholder="90.4125"
                            column="col-md-6 col-sm-6"
                        />
                        @if($gps_lat && $gps_lng)
                            <div class="col-12 mb-2">
                                <a class="small text-success" target="_blank" rel="noopener"
                                   href="https://www.openstreetmap.org/?mlat={{ urlencode($gps_lat) }}&mlon={{ urlencode($gps_lng) }}#map=17/{{ urlencode($gps_lat) }}/{{ urlencode($gps_lng) }}">
                                    <i class="bi bi-geo-alt"></i> {{ __('Open map preview') }}
                                </a>
                            </div>
                        @endif
                    </x-slot>
                    <x-section-border/>
                </x-mikrotik.section-form>
            </div>

            <!-- Step 3: Server Information -->
            @if(!auth()->user()->hasRole('Reseller'))
            <div class="col-12" x-show="isLarge || step === 3" x-transition.opacity>
                @can('mikrotik-user-create')
                    <x-mikrotik.section-form :class="'row'">
                        <x-slot name="title">
                            <span class="text-success fw-bold"><i class="bi bi-hdd-network me-2"></i>{{ __('Server Information') }}</span>
                        </x-slot>
                        <x-slot name="aside">
                            <x-mikrotik.form-group
                                label="{{ __('Router Name') }}"
                                type="dropdown"
                                name="router_name"
                                wChange="getInterface('router_name')"
                                placeholder="{{ __('Select Any One') }}"
                                :options="$routers->pluck('router_name')->toArray()"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Service Type') }}"
                                type="dropdownKey"
                                name="service"
                                placeholder="{{ __('Select Any One') }}"
                                required="true"
                                :options="['static' => __('Static'), 'pppoe' => __('PPPoE')]"
                                wChange="getInterface('service')"
                                :groupstyle="$router_name != '' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Line Status') }}"
                                type="dropdownKey"
                                name="line_status"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['active' => __('Active (online ready)'), 'suspended' => __('Suspended (disabled on router)')]"
                                :groupstyle="$router_name != '' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Profile') }}"
                                type="dropdown"
                                name="profile"
                                wChange="packageName('profile')"
                                placeholder="{{ __('Select Any One') }}"
                                required="true"
                                :options="$profileNames"
                                :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Username/Secrets>Name') }}"
                                type="text"
                                name="username"
                                required="true"
                                placeholder="(eg. FC-40, JohnDoe)"
                                :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Password') }}"
                                type="text"
                                name="password"
                                :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('PPPoE Remote IP Address (Optional)') }}"
                                type="text"
                                name="ppp_remote_ip"
                                :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Interface Name') }}"
                                type="dropdown"
                                name="interface"
                                placeholder="{{ __('Select Any One') }}"
                                required="true"
                                :options="$interfaceNames"
                                :groupstyle="$service == 'static' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Simple Queues > Name') }}"
                                type="text"
                                name="queue_name"
                                required="true"
                                placeholder="(eg. FC-40, JohnDoe)"
                                :groupstyle="$service == 'static' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('IP Address') }}"
                                type="text"
                                name="ip_address"
                                :groupstyle="($router_name && $service == 'static') || !$router_name ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('MAC Address') }}"
                                type="text"
                                name="caller_id"
                                placeholder="(eg. 00:11:22:33:44:55)"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Bandwidth') }}"
                                type="text"
                                name="bandwidth"
                                placeholder="(e.g.,1K/1k, 1K/1M, 1M/1M, 10M/10M)"
                                :required="$service == 'static' ? true : false"
                                :groupstyle="($router_name && $service == 'static') || !$router_name ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Comment') }}"
                                type="text"
                                name="comment"
                            />
                            <x-mikrotik.form-group
                                checkboxLabel="{{ __('Auto Temporary Disable Feature') }}"
                                type="checkbox"
                                column="col-md-4 col-sm-4"
                                name="auto_disable"
                                :groupstyle="$router_name != '' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Expire Date') }}"
                                type="date"
                                column="col-md-4 col-sm-4"
                                name="auto_disable_date"
                                :value="$auto_disable_date"
                                :groupstyle="$router_name != '' ? '' : 'display: none;'"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Auto Temporary Month') }}"
                                type="dropdownKey"
                                column="col-md-4 col-sm-4"
                                name="auto_disable_month"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['0' => __('Current Month'), '1' => __('1st Month'), '2' => __('2nd Month'), '3' => __('3rd Month') , '4' => __('4th Month'), '5' => __('5th Month'), '6' => __('6th Month'), '7' => __('7th Month'), '8' => __('8th Month'), '9' => __('9th Month'), '10' => __('10th Month'), '11' => __('11th Month'), '12' => __('12th Month')]"
                                selectedValue="0"
                                :groupstyle="$router_name != '' ? '' : 'display: none;'"
                            />

                            <div class="col-12 mt-2 mb-1">
                                <hr class="my-2">
                                <span class="text-success fw-bold small"><i class="bi bi-broadcast-pin me-1"></i>{{ __('ONU / Fiber line') }}</span>
                            </div>
                            <x-mikrotik.form-group
                                label="{{ __('Connection Type') }}"
                                type="dropdownKey"
                                name="connection_type"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['fiber' => __('Fiber'), 'wired' => __('Wired'), 'wireless' => __('Wireless')]"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('ONU Ownership') }}"
                                type="dropdownKey"
                                name="onu_ownership"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['company' => __('Company'), 'customer' => __('Customer')]"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('TJ Box / Port') }}"
                                type="text"
                                name="box_name"
                                placeholder="{{ __('e.g. TJ-12 / Port-3') }}"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('EPON Port') }}"
                                type="text"
                                name="epon_port"
                                placeholder="{{ __('e.g. EPON0/4:29') }}"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('ONU MAC') }}"
                                type="text"
                                name="onu_mac"
                                placeholder="{{ __('AA:BB:CC:DD:EE:FF') }}"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Cable Length (m)') }}"
                                type="number"
                                name="cable_length_m"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Drop Cable Direction') }}"
                                type="dropdownKey"
                                name="drop_direction"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['north' => __('North'), 'south' => __('South'), 'east' => __('East'), 'west' => __('West'), 'other' => __('Other')]"
                            />
                            <x-mikrotik.form-group
                                label="{{ __('Drop Fiber Color') }}"
                                type="dropdownKey"
                                name="drop_cable_color"
                                placeholder="{{ __('Select Any One') }}"
                                :options="['blue' => __('Blue'), 'orange' => __('Orange'), 'green' => __('Green'), 'brown' => __('Brown'), 'slate' => __('Slate'), 'white' => __('White'), 'red' => __('Red'), 'black' => __('Black'), 'yellow' => __('Yellow'), 'violet' => __('Violet'), 'rose' => __('Rose'), 'aqua' => __('Aqua')]"
                            />
                        </x-slot>
                        <x-section-border/>
                    </x-mikrotik.section-form>
                @endcan
            </div>
            @endif

            <!-- Step 4 / 3 (Reseller): Billing Information -->
            <div class="col-12" x-show="isLarge || step === @if(!auth()->user()->hasRole('Reseller')) 4 @else 3 @endif" x-transition.opacity>
                <x-mikrotik.section-form :class="'row'">
                    <x-slot name="title">
                        <span class="text-success fw-bold"><i class="bi bi-cash-stack me-2"></i>{{ __('Billing Information') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <x-mikrotik.form-group
                            label="{{ __('Billing Type') }}"
                            type="radio"
                            column="col-md-6 col-sm-6"
                            name="billing_type"
                            :options="['prepaid' => __('Prepaid'), 'postpaid' => __('Postpaid')]"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('First Bill') }}"
                            type="dropdownKey"
                            name="first_bill_cycle"
                            placeholder="{{ __('Select Any One') }}"
                            :options="['this_month' => __('This month (due now)'), 'next_month' => __('Next month (no due yet)')]"
                        />
                        <div class="form-group input-group-sm col-md-4 col-sm-6">
                            <label class="form-label" for="billing_day">{{ __('Bill Day (monthly)') }} <span style="color:red;">*</span></label>
                            <input type="number" min="1" max="28" id="billing_day" class="form-control" wire:model.live="billing_day" placeholder="1–28">
                            <small class="text-muted">{{ __('Sets expire date to this day each month') }}</small>
                        </div>
                        <div class="form-group input-group-sm col-md-4 col-sm-6">
                            <label class="form-label" for="grace_period_days">{{ __('Grace Period (days)') }}</label>
                            <input type="number" min="0" max="90" id="grace_period_days" class="form-control" wire:model="grace_period_days" placeholder="0">
                            <small class="text-muted">{{ __('Line OFF after expire + these days') }}</small>
                        </div>
                        <x-mikrotik.form-group
                            label="{{ __('Expire / Line OFF Date') }}"
                            type="date"
                            name="auto_disable_date"
                            column="col-md-4 col-sm-6"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Package Name') }}"
                            type="dropdown"
                            name="package_name"
                            wChange="calculateTotal('package_name')"
                            placeholder="{{ __('Select Any One') }}"
                            required="true"
                            :options="$packages->pluck('package')->toArray()"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Monthly Charge') }}"
                            type="number"
                            name="monthly_rent"
                            wInput="calculateTotal('monthly_rent')"
                            required="true"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Due Amount') }}"
                            type="number"
                            wInput="calculateTotal('due_amount')"
                            name="due_amount"
                        />
                        <div class="form-group col-md-12 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="apply_line_charges" wire:model.live="apply_line_charges">
                                <label class="form-check-label" for="apply_line_charges">{{ __('Apply line / installation charge now') }}</label>
                            </div>
                        </div>
                        @if($apply_line_charges)
                        <x-mikrotik.form-group
                            label="{{ __('Line / Installation Charge') }}"
                            type="number"
                            name="service_charge"
                        />
                        @endif
                        <x-mikrotik.form-group
                            label="{{ __('Additional Charge') }}"
                            type="number"
                            wInput="calculateTotal('additional_charge')"
                            name="additional_charge"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Discount') }}"
                            type="number"
                            wInput="calculateTotal('discount')"
                            name="discount"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Advance / Opening Balance') }}"
                            type="number"
                            wInput="calculateTotal('advance')"
                            name="advance"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Vat (%)') }}"
                            type="number"
                            wInput="calculateTotal('vat')"
                            name="vat"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Total Amount') }}"
                            type="number"
                            name="total_amount"
                            readonly
                        />
                    </x-slot>
                    <x-section-border/>
                </x-mikrotik.section-form>
            </div>

            <!-- Step 5 / 4 (Reseller): Office Information -->
            <div class="col-12" x-show="isLarge || step === @if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif" x-transition.opacity>
                <x-mikrotik.section-form :class="'row'">
                    <x-slot name="title">
                        <span class="text-success fw-bold"><i class="bi bi-briefcase me-2"></i>{{ __('Office Information') }}</span>
                    </x-slot>
                    <x-slot name="aside">
                        <x-mikrotik.form-group
                            label="{{ __('Type of Connectivity') }}"
                            type="radio"
                            column="col-md-6 col-sm-6"
                            name="connectivity_type"
                            :options="['shared' => __('Shared'), 'dedicated' => __('Dedicated')]"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Billing Category') }}"
                            type="dropdownKey"
                            name="customer_type"
                            placeholder="{{ __('Select Any One') }}"
                            :options="['standard' => __('Standard'), 'free' => __('Free (no bill)'), 'vip' => __('VIP')]"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Type of Client') }}"
                            type="dropdownKey"
                            name="client_type"
                            placeholder="{{ __('Select Any One') }}"
                            :options="['home' => __('Home / Residential'),'commercial' => __('Commercial'),'Corporate' => __('Corporate'), 'business' => __('Business'), 'vip' => __('VIP')]"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Distribution Location Point') }}"
                            type="dropdownKey"
                            name="distribution_location"
                            placeholder="{{ __('Select Any One') }}"
                            :options="['DC' => 'DC', 'NOC' => 'NOC', 'POP'=>'POP']"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Description') }}"
                            type="text"
                            name="description"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Remarks / Notes') }}"
                            type="text"
                            name="note"
                        />
                        @php
                            $usersData =[];
                            if (auth()->user()->hasRole('Super Admin')) {
                                foreach ($users as $user) {
                                    $usersData[$user->id] = $user->name;
                                }
                            } else {
                                $usersData[auth()->id()] = auth()->user()->name;
                            }
                        @endphp
                        <x-mikrotik.form-group
                            label="{{ __('Connected By') }}"
                            type="dropdownKey"
                            name="connected_by"
                            placeholder="{{ __('Select Any One') }}"
                            required="true"
                            :options="$usersData"
                        />
                        <x-mikrotik.form-group
                            label="{{ __('Security Deposit') }}"
                            type="text"
                            name="security_deposit"
                        />
                        <div class="col-12 mt-2 mb-1">
                            <hr class="my-2">
                            <span class="text-success fw-bold small"><i class="bi bi-bell me-1"></i>{{ __('Billing notifications') }}</span>
                        </div>
                        <x-mikrotik.form-group
                            checkboxLabel="{{ __('Auto create bill') }}"
                            type="checkbox"
                            name="bill_create"
                            column="col-md-3 col-sm-6"
                        />
                        <x-mikrotik.form-group
                            checkboxLabel="{{ __('Bill SMS') }}"
                            type="checkbox"
                            name="bill_sms"
                            column="col-md-3 col-sm-6"
                        />
                        <x-mikrotik.form-group
                            checkboxLabel="{{ __('Bill Email') }}"
                            type="checkbox"
                            name="bill_email"
                            column="col-md-3 col-sm-6"
                        />
                        <x-mikrotik.form-group
                            checkboxLabel="{{ __('Continue bill') }}"
                            type="checkbox"
                            name="continue_bill"
                            column="col-md-3 col-sm-6"
                        />
                    </x-slot>
                    <x-section-border/>
                </x-mikrotik.section-form>
            </div>

            <!-- Buttons Layout: Multi-step Navigation for Mobile vs Simple Submit for Large Screens -->
            <div class="col-12 m-0">
                <!-- Mobile Navigation Controls -->
                <div class="d-lg-none d-flex justify-content-between p-2" x-show="!isLarge">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" style="border-radius: 6px;"
                                x-show="step > 1" @click="$wire.validateStepAndGo(step - 1)">
                            <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success px-3 py-1.5" style="border-radius: 6px;"
                                x-show="step < @if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif" @click="$wire.validateStepAndGo(step + 1)">
                            {{ __('Next') }}<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" class="btn btn-sm btn-success px-4 py-1.5" style="border-radius: 6px;"
                                x-show="step === @if(!auth()->user()->hasRole('Reseller')) 5 @else 4 @endif">
                            <i class="bi bi-check-circle me-1"></i>{{ __('Save') }}
                        </button>
                        <button type="reset" class="btn btn-sm btn-outline-danger px-3 py-1.5" style="border-radius: 6px;"
                                x-show="step === 1" wire:click="$refresh">{{ __('Clear') }}</button>
                    </div>
                </div>

                <!-- Desktop Classic Controls -->
                <div class="d-none d-lg-flex justify-content-end gap-2 p-2 mt-3" x-show="isLarge">
                    <button type="submit" class="btn btn-sm btn-success px-4 py-2" style="border-radius: 6px;">
                        <i class="bi bi-check-circle me-1"></i>{{ __('Save') }}
                    </button>
                    <button type="reset" class="btn btn-sm btn-danger px-3 py-2" style="border-radius: 6px;" wire:click="$refresh">
                        <i class="bi bi-x-circle me-1"></i>{{ __('Clear All') }}
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Remove invalid class and errors on focus
            $('input, textarea, select').on('focus', function () {
                $(this).removeClass('is-invalid');
                $(this).nextAll('.invalid-feedback').remove();
            });

            // Listen for Mikrotik error
            Livewire.on('mikrotikError', (interfaces) => {
                Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: interfaces,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "{{ __('Yes, create it!') }}"
                }).then((result) => {
                    if (result.isConfirmed) {
                        @this.createUser();
                    }
                });
            });
        });
    </script>
@endpush
