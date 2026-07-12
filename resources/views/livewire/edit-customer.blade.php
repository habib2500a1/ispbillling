<div class="zoom-in">
    <x-slot name="header">
        <h2 class="h4 font-weight-bold">
            {{ __('Edit Customer') }}
        </h2>
    </x-slot>

    <div x-data="{ isEditing: null, tempValue: {} }" class="row g-2">
        <div class="col-md-6">
            <!-- personel Information Section -->
            <div class="col-md-12">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Customer Information') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <table class="table table-sm text-capitalize">
                                @foreach ($fields['customer'] as $field => $value)
                                    <tr>
                                        <th>{{ __(ucwords(str_replace('_', ' ', $field))) }}:</th>
                                        <td>
                                            @if ($field === 'customer_unique_id' || $field === 'created_at' || $field === 'updated_at')
                                                <span>
                                                    {!! !empty($fields['customer'][$field]) ? $fields['customer'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                                </span>
                                            @elseif ($field === 'photo_url')
                                                @if ($photo_url)
                                                    <div class="mt-3">
                                                        <label>{{ __('Photo Preview:') }}</label>
                                                        <img src="{{ $photo_url->temporaryUrl() }}" class="img-thumbnail" alt="Image Preview" style="max-width: 200px; max-height: 200px;"><button type="button" class="btn btn-white btn-sm text-danger mx-2 fs-4" wire:click="removePhoto"><i class="bi bi-x-circle-fill"></i></button>
                                                    </div>
                                                @elseif ($fields['customer'][$field])
                                                    <div class="mt-3">
                                                        <label>{{ __('Photo Preview:') }}</label>
                                                        <img src="{{ asset($fields['customer'][$field]) }}" class="img-thumbnail" alt="Image Preview" style="max-width: 200px; max-height: 200px;"><button type="button" class="btn btn-white btn-sm text-danger mx-2 fs-4" wire:click="deletePhoto"><i class="bi bi-x-circle-fill"></i></button>
                                                    </div>
                                                @endif
                                                <form wire:submit.prevent="savePhoto">
                                                    <input type="file" name="photo_url" id="photo_url" wire:model='photo_url'>
                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-upload"></i> {{ __('Save') }}</button>
                                                </form>
                                            @else
                                                <span x-show="isEditing !== '{{ $field }}'"
                                                    @click="isEditing = '{{ $field }}';
                                                    tempValue['{{ $field }}'] = @js( $fields['customer'][$field] ?? '' );
                                                    $wire.startEditing('{{ $field }}');"
                                                    style="cursor: pointer; text-decoration: underline dotted;"
                                                    class="link-success">
                                                    {!! !empty($fields['customer'][$field]) ? $fields['customer'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                                </span>
                                            @endif

                                            <div x-show="isEditing === '{{ $field }}'"
                                                @click.away="isEditing = null;
                                                tempValue['{{ $field }}'] = '{{ $fields['customer'][$field] ?? '' }}';
                                                $wire.cancelEditing('{{ $field }}')"
                                                style="display: none;" class="input-group mt-2">

                                                @if ($field === 'status')
                                                    <select x-model="tempValue['{{ $field }}']" class="form-control form-control-sm h-50">
                                                        <option value="">{{ __('Select Status') }}</option>
                                                        <option value="active">{{ __('Active') }}</option>
                                                        <option value="inactive">{{ __('Inactive') }}</option>
                                                        <option value="pending">{{ __('Pending') }}</option>
                                                    </select>
                                                @elseif ($field === 'mobile' || $field === 'alternative_mobile')
                                                    <div wire:ignore class="w-100">
                                                        <input type="text" x-model="tempValue['{{ $field }}']"
                                                            class="form-control form-control-sm h-50 w-100"
                                                            x-data="intlTelInput()"
                                                            placeholder="Edit {{ ucwords(str_replace('_', ' ', $field)) }}" autofocus />
                                                    </div>
                                                @else
                                                    <input type="text" x-model="tempValue['{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="{{ __('Edit') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}" autofocus />
                                                @endif

                                                <button @click="$wire.updateCustomer('{{ $field }}', tempValue['{{ $field }}']);
                                                        isEditing = null"
                                                        class="btn btn-white text-success h-50"><i class="bi bi-check2-circle"></i></button>

                                                <button @click="isEditing = null;
                                                        tempValue['{{ $field }}'] = @js(  $fields['customer'][$field] ?? '' );
                                                        $wire.cancelEditing('{{ $field }}')"
                                                        class="btn btn-white h-50 text-danger "><i class="bi bi-x-circle"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>
            <!-- Address Information Section -->
            <div class="col-md-12 pt-2">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Address Information') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <table class="table table-sm text-capitalize">
                                @foreach ($fields['customerAddress'] as $field => $value)
                                    <tr>
                                        <th>{{ __(ucwords(str_replace('_', ' ', $field))) }}:</th>
                                        <td>
                                            <span x-show="isEditing !== 'customerAddress.{{ $field }}'"
                                                @click="isEditing = 'customerAddress.{{ $field }}';
                                                tempValue['customerAddress.{{ $field }}'] = @js( $fields['customerAddress'][$field] ?? '' );
                                                $wire.startEditing('customerAddress.{{ $field }}');"
                                                style="cursor: pointer; text-decoration: underline dotted;"
                                                class="link-success">
                                                {!! !empty($fields['customerAddress'][$field]) ? $fields['customerAddress'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                            </span>
                                            <div x-show="isEditing === 'customerAddress.{{ $field }}'"
                                                @click.away="isEditing = null;
                                                tempValue['customerAddress.{{ $field }}'] = @js( $fields['customerAddress'][$field] ?? '' );
                                                $wire.cancelEditing('customerAddress.{{ $field }}')"
                                                style="display: none;" class="input-group mt-2">

                                                <!-- Render input type based on $addressField input_type -->
                                                @php
                                                    $fieldType = $addressFields->firstWhere('label', $field)->input_type ?? 'text';
                                                @endphp

                                                @if($fieldType === 'dropdown')
                                                    <select x-model="tempValue['customerAddress.{{ $field }}']"
                                                        class="form-control form-control-sm h-50">
                                                        <option value="">{{ __('Select') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}</option>
                                                        @foreach (json_decode($addressFields->firstWhere('label', $field)->dropdown_list) as $option)
                                                            <option value="{{ $option }}">{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" x-model="tempValue['customerAddress.{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="{{ __('Edit') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}" autofocus />
                                                @endif

                                                <button @click="$wire.updateCustomer('customerAddress.{{ $field }}', tempValue['customerAddress.{{ $field }}']);
                                                        isEditing = null"
                                                        class="btn btn-white text-success h-50"><i class="bi bi-check2-circle"></i></button>

                                                <button @click="isEditing = null;
                                                        tempValue['customerAddress.{{ $field }}'] = @js( $fields['customerAddress'][$field] ?? '' );
                                                        $wire.cancelEditing('customerAddress.{{ $field }}')"
                                                        class="btn btn-white h-50 text-danger"><i class="bi bi-x-circle"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>
            <!-- Billing Information Section -->
            <div class="col-md-12 pt-2">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Billing Information') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <table class="table table-sm text-capitalize">
                                @foreach ($fields['billing'] as $field => $value)
                                    <tr>
                                        <th>{{ __(ucwords(str_replace('_', ' ', $field))) }}:</th>
                                        <td>
                                            <span>
                                                {!! !empty($fields['billing'][$field]) ? $fields['billing'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                            </span>
                                            {{-- <span x-show="isEditing !== 'billing.{{ $field }}'"
                                                @click="isEditing = 'billing.{{ $field }}';
                                                tempValue['billing.{{ $field }}'] = '{{ $fields['billing'][$field] ?? '' }}';
                                                $wire.startEditing('billing.{{ $field }}');"
                                                style="cursor: pointer; text-decoration: underline dotted;"
                                                class="link-success">
                                                {!! !empty($fields['billing'][$field]) ? $fields['billing'][$field] : '<span class="text-danger">Empty</span>' !!}
                                            </span> --}}

                                            {{-- <div x-show="isEditing === 'billing.{{ $field }}'"
                                                @click.away="isEditing = null;
                                                tempValue['billing.{{ $field }}'] = '{{ $fields['billing'][$field] ?? '' }}';
                                                $wire.cancelEditing('billing.{{ $field }}')"
                                                style="display: none;" class="input-group mt-2">

                                                <input type="text" x-model="tempValue['billing.{{ $field }}']"
                                                    class="form-control form-control-sm h-50"
                                                    placeholder="Edit {{ ucwords(str_replace('_', ' ', $field)) }}" autofocus />

                                                <button @click="$wire.updateCustomer('billing.{{ $field }}', tempValue['billing.{{ $field }}']);
                                                        isEditing = null"
                                                        class="btn btn-white text-success h-50"><i class="bi bi-check2-circle"></i></button>

                                                <button @click="isEditing = null;
                                                        tempValue['billing.{{ $field }}'] = '{{ $fields['billing'][$field] ?? '' }}';
                                                        $wire.cancelEditing('billing.{{ $field }}')"
                                                        class="btn btn-white h-50 text-danger"><i class="bi bi-x-circle"></i></button>
                                            </div> --}}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Server Information Section -->
            @if(!auth()->user()->hasRole('Reseller'))
            <div class="col-md-12">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Server Information') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <table class="table table-sm">
                                @foreach ($fields['pppUser'] as $field => $value)
                                    <tr>
                                        @if ($field === 'ppp_user_id' && !empty($fields['pppUser']['ppp_user_id']))
                                            <td colspan="2" class="text-end">
                                                <button type="button" wire:click="deletePPPUser" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                            </td>
                                        @elseif ($field === 'ppp_user_id' && empty($fields['pppUser']['ppp_user_id']))
                                            <td colspan="2" class="text-start">
                                                {{-- Server Information --}}
                                                <form wire:submit.prevent='savePPPUser'>
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Router Name') }}"
                                                        type="dropdown"
                                                        name="router_name"
                                                        wChange="getInterface('router_name')"
                                                        placeholder="{{ __('Select Any One') }}"
                                                        :options="$routers->pluck('router_name')->toArray()"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Service Type') }}"
                                                        type="dropdownKey"
                                                        name="service"
                                                        placeholder="{{ __('Select Any One') }}"
                                                        required="true"
                                                        :options="['static' => __('Static'), 'pppoe' => __('PPPoE')]"
                                                        wChange="getInterface('service')"
                                                        :groupstyle="$router_name != '' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Profile') }}"
                                                        type="dropdown"
                                                        name="profile"
                                                        placeholder="{{ __('Select Any One') }}"
                                                        required="true"
                                                        :options="$profileNames"
                                                        :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Username/Secrets>Name') }}"
                                                        type="text"
                                                        name="username"
                                                        required="true"
                                                        placeholder="(eg. FC-40, JohnDoe)"
                                                        :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Password') }}"
                                                        type="text"
                                                        name="password"
                                                        :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('PPPoE Remote IP Address (Optional)') }}"
                                                        type="text"
                                                        name="ppp_remote_ip"
                                                        :groupstyle="$service == 'pppoe' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Interface Name') }}"
                                                        type="dropdown"
                                                        name="interface"
                                                        placeholder="{{ __('Select Any One') }}"
                                                        required="true"
                                                        :options="$interfaceNames"
                                                        :groupstyle="$service == 'static' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Simple Queues > Name') }}"
                                                        type="text"
                                                        name="queue_name"
                                                        required="true"
                                                        placeholder="(eg. FC-40, JohnDoe)"
                                                        :groupstyle="$service == 'static' ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('IP Address') }}"
                                                        type="text"
                                                        name="ip_address"
                                                        required="true"
                                                        :groupstyle="($router_name && $service == 'static') || !$router_name ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('MAC Address') }}"
                                                        type="text"
                                                        name="caller_id"
                                                        placeholder="(eg. 00:11:22:33:44:55)"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Bandwidth') }}"
                                                        type="text"
                                                        name="bandwidth"
                                                        placeholder="(e.g.,1K/1k, 1K/1M, 1M/1M, 10M/10M)"
                                                        :required="$service == 'static' ? true : false"
                                                        :groupstyle="($router_name && $service == 'static') || !$router_name ? '' : 'display: none;'"
                                                    />
                                                    <x-mikrotik.form-input
                                                        labelClass="col"
                                                        groupClass="col"
                                                        label="{{ __('Comment') }}"
                                                        type="text"
                                                        name="comment"
                                                    />
                                                    <div class="text-end">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Save') }}</button>
                                                        <button wire:click="resetPPPUser" class="btn btn-sm btn-outline-secondary">{{ __('Reset') }}</button>
                                                    </div>
                                                </form>
                                            </td>
                                        @else
                                            <th>{{ __(ucwords(str_replace('_', ' ', $field))) }}:</th>
                                            <td>
                                                @if ($field === 'auto_disable')
                                                    <span>
                                                        <x-mikrotik.form-input
                                                            type="checkbox"
                                                            name="fields.pppUser.{{ $field }}"
                                                            wChange="checkboxUpdated('fields.pppUser.{{ $field }}')"
                                                            :value="$fields['pppUser'][$field] ?? ''"
                                                            :checked="isset($fields['pppUser'][$field]) && $fields['pppUser'][$field] == 1"
                                                        />
                                                    </span>
                                                @elseif ($field === 'router_name' || $field === 'service')
                                                    <span>{{ $fields['pppUser'][$field] ?? '' }}</span>
                                                @else
                                                    <span x-show="isEditing !== 'pppUser.{{ $field }}'"
                                                        @click="isEditing = 'pppUser.{{ $field }}';
                                                        tempValue['pppUser.{{ $field }}'] = @js($fields['pppUser'][$field] ?? '');
                                                        $wire.startEditing('pppUser.{{ $field }}');"
                                                        style="cursor: pointer; text-decoration: underline dotted;"
                                                        class="link-success">
                                                        {!! !empty($fields['pppUser'][$field]) ? $fields['pppUser'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                                    </span>
                                                @endif
 
                                                <div x-show="isEditing === 'pppUser.{{ $field }}'"
                                                    @click.away="isEditing = null;
                                                    tempValue['pppUser.{{ $field }}'] = '{{ $fields['pppUser'][$field] ?? '' }}';
                                                    $wire.cancelEditing('pppUser.{{ $field }}')"
                                                    style="display: none;" class="input-group mt-2">
                                                    @if ($field === 'connection_date')
                                                        <input type="date" x-model="tempValue['pppUser.{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="Edit {{ ucwords(str_replace('_', ' ', $field)) }}" autofocus />
                                                    @elseif ($field === 'package_name')
                                                        <select x-model="tempValue['pppUser.{{ $field }}']"
                                                            class="form-control form-control-sm h-50">
                                                            <option value="">{{ __('Select') }} {{ ucwords(str_replace('_', ' ', $field)) }}</option>
                                                            @foreach ($packageLists as $packageList)
                                                                <option value="{{ $packageList }}">{{ $packageList }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($field === 'profile')
                                                        <select x-model="tempValue['pppUser.{{ $field }}']"
                                                            class="form-control form-control-sm h-50">
                                                            <option value="">{{ __('Select') }} {{ ucwords(str_replace('_', ' ', $field)) }}</option>
                                                            @foreach ($interfaceNames as $interfaceName)
                                                                <option value="{{ $interfaceName }}">{{ $interfaceName }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($field === 'auto_disable_date')
                                                        <input type="date" x-model="tempValue['pppUser.{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="{{ __('Edit') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}" autofocus />
                                                    @elseif ($field === 'auto_disable_month')
                                                        <select x-model="tempValue['pppUser.{{ $field }}']"
                                                            class="form-control form-control-sm h-50">
                                                            <option value="">{{ __('Select') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}</option>
                                                            <option value="0">{{ __('Current Month') }}</option>
                                                            <option value="1">{{ __('1st Month') }}</option>
                                                            <option value="2">{{ __('2nd Month') }}</option>
                                                            <option value="3">{{ __('3rd Month') }}</option>
                                                            <option value="4">{{ __('4th Month') }}</option>
                                                            <option value="5">{{ __('5th Month') }}</option>
                                                            <option value="6">{{ __('6th Month') }}</option>
                                                            <option value="7">{{ __('7th Month') }}</option>
                                                            <option value="8">{{ __('8th Month') }}</option>
                                                            <option value="9">{{ __('9th Month') }}</option>
                                                            <option value="10">{{ __('10th Month') }}</option>
                                                            <option value="11">{{ __('11th Month') }}</option>
                                                            <option value="12">{{ __('12th Month') }}</option>
                                                        </select>
                                                    @else
                                                        <input type="text" x-model="tempValue['pppUser.{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="{{ __('Edit') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}" autofocus />
                                                    @endif
 
                                                    <button @click="$wire.updateCustomer('pppUser.{{ $field }}', tempValue['pppUser.{{ $field }}']);
                                                            isEditing = null"
                                                            class="btn btn-white text-success h-50"><i class="bi bi-check2-circle"></i></button>
 
                                                    <button @click="isEditing = null;
                                                            tempValue['pppUser.{{ $field }}'] = @js(  $fields['pppUser'][$field] ?? '' );
                                                            $wire.cancelEditing('pppUser.{{ $field }}')"
                                                            class="btn btn-white h-50 text-danger"><i class="bi bi-x-circle"></i></button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>
            @endif

            <!-- Network path (Phase 2 — ispbilling-style, Code Pagol design) -->
            <div class="col-md-12 pt-2">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Network Path') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <span class="text-muted small font-monospace">{{ $networkPath['path_label'] ?? '—' }}</span>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info" wire:click="refreshNetworkPath" wire:loading.attr="disabled">
                                        <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="linkOnuAuto" wire:loading.attr="disabled">
                                        <i class="bi bi-link-45deg"></i> {{ __('Link ONU') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" wire:click="setLineActive" wire:confirm="{{ __('Enable this line on MikroTik (Net ON)?') }}">
                                        {{ __('Net ON') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="setLineDisabled" wire:confirm="{{ __('Disable this line on MikroTik (Net OFF)?') }}">
                                        {{ __('Net OFF') }}
                                    </button>
                                </div>
                            </div>

                            <table class="table table-sm mb-0">
                                <tr>
                                    <th style="width: 30%;">{{ __('MikroTik') }}</th>
                                    <td>{{ $networkPath['mikrotik']['name'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('PPP Login') }}</th>
                                    <td class="font-monospace">
                                        {{ $networkPath['ppp']['login'] ?? '—' }}
                                        @if(!empty($networkPath['ppp']['online']))
                                            <span class="badge bg-success ms-1">{{ __('Online') }}</span>
                                        @else
                                            <span class="badge bg-secondary ms-1">{{ __('Offline') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('WAN IP') }}</th>
                                    <td class="font-monospace">{{ $networkPath['ppp']['framed_ip'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Router MAC') }}</th>
                                    <td class="font-monospace">{{ $networkPath['ppp']['caller_id'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('ONU / PON') }}</th>
                                    <td>
                                        @if(!empty($networkPath['onu']['linked']))
                                            <span class="font-monospace">{{ $networkPath['onu']['epon'] ?? '—' }}</span>
                                            @if(isset($networkPath['onu']['rx_dbm']) && $networkPath['onu']['rx_dbm'] !== null)
                                                · RX {{ number_format($networkPath['onu']['rx_dbm'], 2) }} dBm
                                            @endif
                                            <div class="small text-muted">{{ $networkPath['onu']['olt'] ?? '' }} · {{ $networkPath['onu']['mac'] ?? '' }}</div>
                                        @else
                                            <span class="text-muted">{{ __('ONU not linked — use Link ONU') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('Customer status') }}</th>
                                    <td>
                                        <span class="badge bg-{{ ($networkPath['customer_status'] ?? '') === 'active' ? 'success' : (($networkPath['customer_status'] ?? '') === 'disable' ? 'danger' : 'secondary') }}">
                                            {{ $networkPath['customer_status'] ?? '—' }}
                                        </span>
                                        <span class="text-muted small ms-2">PPP: {{ $networkPath['ppp']['status'] ?? '—' }}</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>

            <!-- Optical / ONU (from ispbilling-style customer view) -->
            <div class="col-md-12 pt-2">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Optical / ONU') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">{{ __('Fiber signal from linked ONU') }}</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-info" wire:click="syncOpticalFromIspbilling" wire:loading.attr="disabled">
                                        <i class="bi bi-arrow-repeat"></i> {{ __('Sync') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-link text-info p-0" wire:click="toggleOpticalDetails">
                                        {{ __('Details') }} →
                                    </button>
                                </div>
                            </div>

                            @if ($optical['linked'] ?? false)
                                @php $row = $optical['row'] ?? []; @endphp
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th style="width: 30%;">RX</th>
                                        <td class="font-monospace">{{ isset($row['optical_power']) && $row['optical_power'] !== null ? $row['optical_power'].' dBm' : '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>TX</th>
                                        <td class="font-monospace">{{ isset($row['tx_power']) && $row['tx_power'] !== null ? $row['tx_power'].' dBm' : '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>OLT</th>
                                        <td>{{ $row['olt_name'] ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>PON</th>
                                        <td class="font-monospace">{{ $row['olt_port'] ?? '—' }}</td>
                                    </tr>
                                </table>
                            @else
                                <p class="text-muted small mb-2">{{ $optical['hint'] ?? __('ONU not linked.') }}</p>
                            @endif

                            @if ($showOpticalDetails)
                                <hr>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-0">{{ __('OLT') }}</label>
                                        <input type="text" class="form-control form-control-sm" wire:model="onu_olt_name" placeholder="OLT-...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-0">{{ __('PON') }}</label>
                                        <input type="text" class="form-control form-control-sm" wire:model="onu_pon_port" placeholder="EPON0/2:5">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">RX (dBm)</label>
                                        <input type="text" class="form-control form-control-sm" wire:model="onu_rx" placeholder="-13.50">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">TX (dBm)</label>
                                        <input type="text" class="form-control form-control-sm" wire:model="onu_tx" placeholder="1.70">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">MAC</label>
                                        <input type="text" class="form-control form-control-sm" wire:model="onu_mac">
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveOpticalManual">
                                            <i class="bi bi-save"></i> {{ __('Save Optical') }}
                                        </button>
                                        @if (!empty($optical['details']['source']))
                                            <span class="text-muted small ms-2">{{ __('Source') }}: {{ $optical['details']['source'] }}
                                                @if (!empty($optical['details']['last_polled_at']))
                                                    · {{ $optical['details']['last_polled_at'] }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>

            <!-- official Information Section -->
            <div class="col-md-12 pt-2">
                <x-mikrotik.section-form>
                    <x-slot name="title">{{ __('Official Information') }}</x-slot>
                    <x-slot name="aside">
                        <div class="col-12">
                            <table class="table table-sm text-capitalize">
                                @foreach ($fields['official'] as $field => $value)
                                    <tr>
                                        <th>{{ $field === 'reseller_id' ? __('Shifted to Reseller') : __(ucwords(str_replace('_', ' ', $field))) }}:</th>
                                        <td>
 
                                            @if ($field === 'bill_sms' || $field === 'continue_bill' || $field === 'bill_create')
                                                <span>
                                                    <x-mikrotik.form-input
                                                        type="checkbox"
                                                        name="fields.official.{{ $field }}"
                                                        wChange="checkboxUpdated('fields.official.{{ $field }}')"
                                                        :value="$fields['official'][$field] ?? ''"
                                                        :checked="isset($fields['official'][$field]) && $fields['official'][$field] == 1"
                                                    />
                                                </span>
                                            @else
                                                <span x-show="isEditing !== 'official.{{ $field }}'"
                                                    @click="isEditing = 'official.{{ $field }}';
                                                    tempValue['official.{{ $field }}'] = @js( $field === 'connected_by' ? ($userLists->where('name', $fields['official'][$field])->first()->id ?? '') : ($field === 'reseller_id' ? ($resellersList->where('company', $fields['official'][$field])->first()->id ?? $resellersList->where('user.name', $fields['official'][$field])->first()->id ?? '') : ($fields['official'][$field] ?? '')) );
                                                    $wire.startEditing('official.{{ $field }}');"
                                                    style="cursor: pointer; text-decoration: underline dotted;"
                                                    class="link-success">
                                                    {!! !empty($fields['official'][$field]) ? $fields['official'][$field] : '<span class="text-danger">' . __('Empty') . '</span>' !!}
                                                </span>
                                            @endif

                                            <div x-show="isEditing === 'official.{{ $field }}'"
                                                @click.away="isEditing = null;
                                                tempValue['official.{{ $field }}'] = '{{ $fields['official'][$field] ?? '' }}';
                                                $wire.cancelEditing('official.{{ $field }}')"
                                                style="display: none;" class="input-group mt-2">

                                                @if ($field === 'status' || $field === 'client_type' || $field === 'billing_type' || $field === 'connection_type' || $field === 'connectivity_type' || $field === 'distribution_location' || $field === 'connected_by' || $field === 'reseller_id')
                                                    <select x-model="tempValue['official.{{ $field }}']"
                                                            class="form-control form-control-sm h-50">
                                                        <option value="">{{ $field === 'reseller_id' ? __('No Reseller (Admin)') : (__('Select') . ' ' . __(ucwords(str_replace('_', ' ', $field)))) }}</option>
                                                        @if($field === 'status')
                                                            <option value="active">{{ __('Active') }}</option>
                                                            <option value="disable">{{ __('Temporary Disable') }}</option>
                                                            @if(!auth()->user()->hasRole('Reseller'))
                                                                <option value="free">{{ __('Free') }}</option>
                                                            @endif
                                                        @elseif ($field === 'client_type')
                                                            <option value="home">{{ __('Home') }}</option>
                                                            <option value="commercial">{{ __('Commercial') }}</option>
                                                            <option value="corporate">{{ __('Corporate') }}</option>
                                                            <option value="business">{{ __('Business') }}</option>
                                                        @elseif ($field === 'billing_type')
                                                            <option value="prepaid">{{ __('Prepaid') }}</option>
                                                            <option value="postpaid">{{ __('Postpaid') }}</option>
                                                        @elseif ($field === 'connection_type')
                                                            <option value="fiber">{{ __('Fiber') }}</option>
                                                            <option value="wired">{{ __('Wired') }}</option>
                                                            <option value="wireless">{{ __('Wireless') }}</option>
                                                        @elseif ($field === 'connectivity_type')
                                                            <option value="shared">{{ __('Shared') }}</option>
                                                            <option value="dedicated">{{ __('Dedicated') }}</option>
                                                        @elseif ($field === 'distribution_location')
                                                            <option value="dc">{{ __('DC') }}</option>
                                                            <option value="noc">{{ __('NOC') }}</option>
                                                            <option value="pop">{{ __('POP') }}</option>
                                                        @elseif ($field === 'connected_by')
                                                            @foreach ($userLists as $userList)
                                                                <option value="{{ $userList->id }}">{{ $userList->name }}</option>
                                                            @endforeach
                                                        @elseif ($field === 'reseller_id')
                                                            @foreach ($resellersList as $rItem)
                                                                <option value="{{ $rItem->id }}">
                                                                    {{ $rItem->company ? $rItem->company . ' (' . $rItem->user->name . ')' : $rItem->user->name }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                @else
                                                    <input type="text" x-model="tempValue['official.{{ $field }}']"
                                                        class="form-control form-control-sm h-50"
                                                        placeholder="{{ __('Edit') }} {{ __(ucwords(str_replace('_', ' ', $field))) }}" autofocus />
                                                @endif
                                                <button @click="$wire.updateCustomer('official.{{ $field }}', tempValue['official.{{ $field }}']);
                                                        isEditing = null"
                                                        class="btn btn-white text-success h-50"><i class="bi bi-check2-circle"></i></button>

                                                <button @click="isEditing = null;
                                                        tempValue['official.{{ $field }}'] = @js(  $fields['official'][$field] ?? '' );
                                                        $wire.cancelEditing('official.{{ $field }}')"
                                                        class="btn btn-white h-50 text-danger"><i class="bi bi-x-circle"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </x-slot>
                </x-mikrotik.section-form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // remove invalid feedback and error
            $('input, textarea, select').on('focus', function () {
                $(this).removeClass('is-invalid'); // remove invalid class
                $(this).nextAll('.invalid-feedback').remove(); // remove invalid feedback
            });
        });
    </script>
@endpush
