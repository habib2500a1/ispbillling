<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\AddressField;
use App\Models\BillingInfo;
use App\Models\CustomersAddress;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\Reseller;
use App\Models\RouterList;
use App\Models\User;
use App\Rules\ValidPhoneDigits;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditCustomer extends Component
{
    use WithFileUploads;

    public $customerId;

    public $fields = [];

    public $tempFields = [];

    public $ppp_user_id;

    public $routers;

    public $router_name;

    public $interface;

    public $ip_address;

    public $bandwidth;

    public $caller_id;

    public $profile;

    public $service;

    public $queue_name;

    public $auto_disable_date;

    public $username;

    public $password;

    public $data = [];

    public $auto_disable;

    public $tempValue;

    public $ppp_remote_ip;

    public $comment;

    // public $dropdownOptions = [
    //     'customer_type' => ['Individual', 'Corporate'],
    //     'billing_status' => ['Active', 'Inactive', 'Pending'],
    // ];
    public $customerAddress = [];

    public $addressFields;

    public $packageLists = [];

    public $profileNames = [];

    public string $quickClientType = 'home';

    public $photo_url;

    public $userLists;

    public $resellersList = [];

    public $interfaceNames = [];

    public array $optical = [];

    public bool $showOpticalDetails = false;

    public string $onu_olt_name = '';

    public string $onu_pon_port = '';

    public string $onu_rx = '';

    public string $onu_tx = '';

    public string $onu_mac = '';

    public array $networkPath = [];

    public string $quickPackage = '';

    public string $quickCustomerType = 'standard';

    public string $quickStatus = '';

    public bool $embedded = false;

    public function mount($customerId, bool $embedded = false)
    {
        $this->embedded = $embedded;
        if (! hasAccess(['Super Admin'], ['edit-customer']) && ! auth()->user()->hasRole('Reseller')) {
            abort(403, 'Unauthorized action.');
        }

        $this->addressFields = AddressField::all();
        $this->routers = RouterList::all();
        $this->userLists = User::select('id', 'name', 'email')->get();
        if (hasAccess(['Super Admin'], ['create-reseller'])) {
            $this->resellersList = Reseller::with('user')->get();
        }
        $this->customerId = $customerId;
        $this->loadCustomerData($customerId);
        $this->profileNames = PackageList::namesForRouter($this->fields['pppUser']['router_name'] ?? null);
        $this->loadOptical(false);
    }

    public function loadNetworkPath(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with(['pppUser', 'onus'])
            ->first();

        if (! $customer) {
            $this->networkPath = [];

            return;
        }

        $this->networkPath = app(\App\Services\Network\SubscriberNetworkPathService::class)
            ->path($customer);
    }

    public function refreshNetworkPath(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with(['pppUser', 'onus'])
            ->first();

        if (! $customer) {
            flash()->error(__('Customer not found.'));

            return;
        }

        $this->networkPath = app(\App\Services\Network\SubscriberNetworkPathService::class)
            ->syncAndRefresh($customer);
        $this->loadOptical(false);
        flash()->success(__('Network path refreshed.'));
    }

    public function linkOnuAuto(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('pppUser')
            ->first();

        if (! $customer) {
            flash()->error(__('Customer not found.'));

            return;
        }

        $onu = app(\App\Services\Olt\IspbillingOpticalBridge::class)->autoLinkCustomer($customer);
        $this->loadOptical(false);
        $this->loadNetworkPath();

        if ($onu) {
            flash()->success(__('ONU linked: :pon · RX :rx dBm', [
                'pon' => $onu->pon_port ?: '—',
                'rx' => $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2) : '—',
            ]));
        } else {
            flash()->warning(__('No ONU match for this PPP/MAC.'));
        }
    }

    public function setLineActive(): void
    {
        $this->setLineStatus('active');
    }

    public function setLineDisabled(): void
    {
        $this->setLineStatus('disable');
    }

    protected function setLineStatus(string $status): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('pppUser')
            ->first();

        if (! $customer || ! $customer->pppUser) {
            flash()->error(__('PPP user not found for this customer.'));

            return;
        }

        $ppp = $customer->pppUser;
        $unique = decrypt($this->customerId);

        try {
            if ($status === 'active') {
                app(MikrotikController::class)->enablePPPSecret($unique, $ppp->router_name, $ppp->username);
                PPPSecrets::where('id', $ppp->id)->update(['status' => 'active']);
                $customer->update(['status' => 'active']);
                flash()->success(__('Line enabled (Net ON).'));
            } else {
                app(MikrotikController::class)->disablePPPSecret($unique, $ppp->router_name, $ppp->username);
                PPPSecrets::where('id', $ppp->id)->update(['status' => 'disable']);
                $customer->update(['status' => 'disable']);
                flash()->success(__('Line disabled (Net OFF).'));
            }
            $this->loadCustomerData($this->customerId);
            $this->loadNetworkPath();
        } catch (\Throwable $e) {
            flash()->error(__('Line action failed: :msg', ['msg' => $e->getMessage()]));
        }
    }

    public function loadOptical(bool $tryRemote = true): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('pppUser')
            ->first();

        if (! $customer) {
            $this->optical = ['linked' => false, 'hint' => null, 'row' => null, 'details' => []];

            return;
        }

        $this->optical = app(\App\Services\Olt\CustomerOpticalPresenter::class)
            ->forCustomer($customer, $tryRemote);

        $row = $this->optical['row'] ?? null;
        $details = $this->optical['details'] ?? [];
        $this->onu_olt_name = (string) ($row['olt_name'] ?? '');
        $this->onu_pon_port = (string) (($row['olt_port'] ?? '') === '—' ? '' : ($row['olt_port'] ?? ''));
        $this->onu_rx = (string) ($row['optical_power'] ?? '');
        $this->onu_tx = (string) ($row['tx_power'] ?? '');
        $this->onu_mac = (string) ($details['mac'] ?? '');
    }

    public function syncOpticalFromIspbilling(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('pppUser')
            ->first();

        if (! $customer) {
            flash()->error(__('Customer not found.'));

            return;
        }

        $synced = app(\App\Services\Olt\IspbillingOpticalBridge::class)->syncForCustomer($customer);
        $this->loadOptical(false);

        if ($synced) {
            flash()->success(__('Optical / ONU synced.'));
        } else {
            flash()->warning(__('No matching ONU found for this PPP username.'));
        }
    }

    public function saveOpticalManual(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))->first();
        if (! $customer) {
            flash()->error(__('Customer not found.'));

            return;
        }

        app(\App\Services\Olt\CustomerOpticalPresenter::class)->saveManual($customer, [
            'olt_name' => $this->onu_olt_name ?: null,
            'pon_port' => $this->onu_pon_port ?: null,
            'rx_power_dbm' => $this->onu_rx !== '' ? $this->onu_rx : null,
            'tx_power_dbm' => $this->onu_tx !== '' ? $this->onu_tx : null,
            'mac_address' => $this->onu_mac ?: null,
        ]);

        $this->loadOptical(false);
        $this->showOpticalDetails = false;
        flash()->success(__('Optical / ONU saved.'));
    }

    public function toggleOpticalDetails(): void
    {
        $this->showOpticalDetails = ! $this->showOpticalDetails;
    }

    public function loadPackageLists($customer)
    {
        if ($customer && $customer->reseller_id) {
            $reseller = Reseller::find($customer->reseller_id);
            if ($reseller) {
                $this->packageLists = PackageList::where(function ($q) use ($reseller) {
                    $q->whereIn('id', $reseller->assignedPackages->pluck('id'))
                        ->orWhere('reseller_id', $reseller->id);
                })
                    ->orderBy('package')
                    ->pluck('package')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $this->ensureCurrentPackageInList();

                return;
            }
        }

        $routerName = $this->fields['pppUser']['router_name'] ?? null;
        $this->packageLists = PackageList::namesForRouter($routerName ?: null);
        $this->ensureCurrentPackageInList();
    }

    protected function ensureCurrentPackageInList(): void
    {
        $current = $this->fields['pppUser']['package_name'] ?? '';
        if ($current !== '' && ! in_array($current, $this->packageLists, true)) {
            $this->packageLists[] = $current;
        }
    }

    protected function findPackageForCustomer(CustomersInfo $customer, string $packageName): ?PackageList
    {
        if ($customer->reseller_id) {
            $reseller = Reseller::find($customer->reseller_id);

            return PackageList::where('package', $packageName)
                ->where(function ($q) use ($reseller) {
                    $q->whereIn('id', $reseller->assignedPackages->pluck('id'))
                        ->orWhere('reseller_id', $reseller->id);
                })
                ->first();
        }

        $router = $customer->pppUser->router_name ?? null;

        return PackageList::where('package', $packageName)
            ->where(function ($q) use ($router) {
                if ($router) {
                    $q->where('router_name', $router)->orWhereNull('router_name');
                } else {
                    $q->whereNull('router_name');
                }
            })
            ->first();
    }

    #[On('save-quick-fields')]
    public function saveQuickFields(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('billing', 'official', 'pppUser')
            ->first();

        if (! $customer) {
            flash()->error(__('Customer not found.'));

            return;
        }

        if (auth()->user()->hasRole('Reseller')) {
            $reseller = auth()->user()->reseller;
            if (! $reseller || $customer->reseller_id !== $reseller->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if ($this->quickPackage !== '') {
            $pkg = $this->findPackageForCustomer($customer, $this->quickPackage);
            if (! $pkg) {
                flash()->error(__('Package not found.'));

                return;
            }
            $customer->package_id = $pkg->id;
            if ($customer->billing) {
                $customer->billing->monthly_rent = $pkg->price;
                $this->recalculateBillingTotal($customer->billing);
            }
            data_set($this->fields, 'pppUser.package_name', $this->quickPackage);
        }

        if ($customer->official) {
            $customer->official->customer_type = $this->quickCustomerType ?: 'standard';
            $customer->official->client_type = $this->quickClientType ?: 'home';
            $customer->official->save();
            data_set($this->fields, 'official.customer_type', $this->quickCustomerType);
            data_set($this->fields, 'official.client_type', $this->quickClientType);
        }

        $statusChanged = $this->quickStatus !== '' && $this->quickStatus !== ($customer->status ?? '');
        $customer->save();

        if ($statusChanged) {
            $this->updateCustomer('official.status', $this->quickStatus);
            $this->loadCustomerData($this->customerId);

            return;
        }

        $this->loadCustomerData($this->customerId);
        flash()->success(__('Customer updated successfully.'));
    }

    public function loadInterfaceNames()
    {
        if ($this->fields['pppUser']['router_name'] ?? false) {
            $routerName = $this->fields['pppUser']['router_name'];

            // Router details
            $router = RouterList::where('router_name', $routerName)->first();

            if ($router) {
                $this->profileNames = [];
                if ($router->action === 'connected') {
                    $this->profileNames = $this->readMikrotikNames(
                        $routerName,
                        '/ppp/profile/print',
                        '/ppp profile print without-paging terse'
                    );
                }
                if ($this->profileNames === []) {
                    $this->profileNames = PackageList::namesForRouter($routerName);
                }
            }
        }
    }

    public function loadCustomerData($customerId)
    {
        $customer = CustomersInfo::where('customer_unique_id', decrypt($customerId))
            ->with('customerAddress', 'billing', 'official', 'pppUser', 'package')
            ->first();

        if ($customer) {
            if (auth()->user()->hasRole('Reseller')) {
                $reseller = auth()->user()->reseller;
                if (! $reseller || $customer->reseller_id !== $reseller->id) {
                    abort(403, 'Unauthorized action.');
                }
            }
            $addressFields = $this->addressFields; // Retrieve all AddressField entries

            $customerAddresses = $addressFields->mapWithKeys(function ($field) use ($customer) {
                $matchedValue = ''; // Default value

                // Loop through each address and find the first non-empty value for each field label
                foreach ($customer->customerAddress as $address) {
                    $inputTypeKey = 'input_type_'.$field->input_type;

                    // Check if the address field has a value for the current label
                    if (! empty($address->{$inputTypeKey}) && $address->label_name === $field->label) {
                        $matchedValue = $address->{$inputTypeKey};
                        break; // Stop after finding the first non-empty matching value
                    }
                }

                return [$field->label => $matchedValue];
            })->toArray();

            $this->ppp_user_id = $customer->ppp_user_id;
            // Initialize fields for the customer and related data
            $this->fields = [
                'customer' => [
                    'customer_unique_id' => $customer->customer_unique_id ?? '',
                    'customer_name' => $customer->customer_name ?? '',
                    'contact_person' => $customer->contact_person ?? '',
                    'parents_name' => $customer->parents_name ?? '',
                    'spouse_name' => $customer->spouse_name ?? '',
                    'mobile' => $customer->mobile ?? '',
                    'alternative_mobile' => $customer->alternative_mobile ?? '',
                    'email' => $customer->email ?? '',
                    'identification_no' => $customer->identification_no ?? '',
                    'profession' => $customer->profession ?? '',
                    'created_at' => Carbon::parse($customer->created_at)->format('d M Y, h:i:s A') ?? '',
                    'photo_url' => $customer->photo_url ?? '',
                ],
                'billing' => [
                    'monthly_rent' => $customer->billing->monthly_rent ?? '',
                    'additional_charge' => $customer->billing->additional_charge ?? '',
                    'discount' => $customer->billing->discount ?? '',
                    'advance' => $customer->billing->advance ?? '',
                    'previous_due' => $customer->billing->previous_due ?? '',
                    'vat' => $customer->billing->vat ?? '',
                    'total_amount' => $customer->billing->total_amount ?? '',
                    'billing_type' => $customer->billing->billing_type ?? '',
                    // Add more billing-related fields
                ],
                'customerAddress' => $customerAddresses, // This will be an array of addresses

                'pppUser' => array_merge([
                    'connection_date' => $customer->joinDate()?->format('Y-m-d') ?? '',
                    'package_name' => $customer->package?->package ?? '',
                    'ppp_user_id' => $this->ppp_user_id ?? '',
                ], $this->ppp_user_id !== null ? [
                    'router_name' => $customer->pppUser->router_name ?? '',
                    'username' => $customer->pppUser->username ?? '',
                    'password' => $customer->pppUser->password ?? '',
                    'service' => $customer->pppUser->service ?? '',
                    'profile' => $customer->pppUser->profile ?? '',
                    'caller_id' => $customer->pppUser->caller_id ?? '',
                    'comment' => $customer->pppUser->comment ?? '',
                    'ppp_remote_ip' => $customer->pppUser->ppp_remote_ip ?? '',
                    'bandwidth' => $customer->pppUser->bandwidth ?? '',
                ] : [],
                    [
                        'auto_disable_date' => $customer->billing?->auto_disable_date ? Carbon::parse($customer->billing->auto_disable_date)->format('d M Y') : '',
                        'auto_disable_month' => $customer->billing?->auto_disable_month ? $customer->billing->auto_disable_month.' Month' : '',
                        'auto_disable' => $customer->billing->auto_disable ?? '',
                    ]
                ),

                'official' => array_merge(
                    [
                        'service_charge' => $customer->official->service_charge ?? '',
                        'security_deposit' => $customer->official->security_deposit ?? '',
                        'client_type' => $customer->official->client_type ?? '',
                        'billing_type' => $customer->official->billing_type ?? '',
                        'connection_type' => $customer->official->connection_type ?? '',
                        'connectivity_type' => $customer->official->connectivity_type ?? '',
                        'distribution_location' => $customer->official->distribution_location ?? '',
                        'customer_type' => $customer->official->customer_type ?? 'standard',
                        'bill_create' => $customer->official->bill_create ?? true,
                        'bill_sms' => $customer->official->bill_sms ?? '',
                        'continue_bill' => $customer->official->continue_bill ?? '',
                        'description' => $customer->official->description ?? '',
                        'note' => $customer->official->note ?? '',
                        'connected_by' => $customer->official?->connected_by ? ($this->userLists->where('id', $customer->official->connected_by)->first()->name ?? '') : '',
                    ],
                    hasAccess(['Super Admin'], ['create-reseller']) ? ['reseller_id' => $customer->reseller_id && $customer->reseller ? ($customer->reseller->company ? $customer->reseller->company . ' (' . $customer->reseller->user->name . ')' : $customer->reseller->user->name) : ''] : [],
                    [
                        'status' => $customer->status ?? '',
                    ]
                ),
            ];

            // Initialize class-level properties linked to the UI
            $this->router_name = $customer->pppUser->router_name ?? '';
            $this->service = $customer->pppUser->service ?? '';
            $this->auto_disable = $customer->billing->auto_disable ?? true;
            $this->auto_disable_date = $customer->billing->auto_disable_date ?? null;
            $this->quickPackage = $customer->package?->package ?? ($this->fields['pppUser']['package_name'] ?? '');
            $this->quickCustomerType = $customer->official->customer_type ?? 'standard';
            $this->quickClientType = $customer->official->client_type ?? 'home';
            $this->quickStatus = $customer->status ?? '';
            $this->loadPackageLists($customer);
        } else {
            flash()->error('Customer not found.');
        }
    }

    public function resetPPPUser()
    {
        $this->auto_disable_date = $this->ip_address = $this->interface = $this->queue_name = $this->profile = $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = $this->bandwidth = $this->service = null;
    }

    public function deletePPPUser()
    {
        if (auth()->user()->hasRole('Reseller')) {
            abort(403, 'Unauthorized action.');
        }
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))->with('pppUser')->first();

        // Remove PPP secret from router using the correct [find name=...] selector
        try {
            app(MikrotikController::class)->singleWrite(
                $customer->pppUser->router_name,
                '/ppp secret remove [find name="'.$customer->pppUser->username.'"]'
            );
        } catch (\Exception $routerEx) {
            // Log but continue — user may have already been removed from router
            \Log::warning('deletePPPUser router error: '.$routerEx->getMessage());
            flash()->warning('Router warning: '.$routerEx->getMessage().'. Cleaning up database record.');
        }

        // Always clean up the database record regardless of router outcome
        PPPSecrets::where('id', $this->ppp_user_id)->first()->delete();
        if($customer->reseller_id != null) {
            $resellerName = $customer->reseller?->user?->name ?? 'N/A';
            if ($customer->reseller?->company) {
                $resellerName = $customer->reseller->company . ' (' . $resellerName . ')';
            }

            activity()
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->withProperties([
                    'Reseller Info' => [
                        'customer_unique_id' => $customer->customer_unique_id,
                        'customer_name' => $customer->customer_name,
                        'customer_mobile' => $customer->mobile,
                        'reseller_id' => $customer->reseller_id,
                        'reseller_name' => $resellerName,
                        'customer_status' => $customer->status,
                    ],
                ])
                ->log("Deleted PPP User for customer {$customer->customer_name} (ID: {$customer->customer_unique_id}) and unmapped from reseller {$resellerName}.");
        }
        $customer->update([
            'status' => 'inactive',
            'reseller_id' => null,
        ]);
        flash()->warning('Customer PPP User deleted successfully!');
        $this->ppp_user_id = null;
        $this->loadCustomerData($this->customerId);
    }

    public function getInterface($propertyName)
    {
        if (in_array($propertyName, ['service', 'router_name'])) {
            $this->data = [
                'service' => $this->service,
                'router_name' => $this->router_name,
            ];
            // Proceed only if service is static and router_name is set
            // auto disable date will be set only if router_name is set
            $normalizedRouterName = ! empty($this->router_name) ? $this->router_name : null;
            if ($normalizedRouterName) {
                $this->auto_disable = true;
                $this->auto_disable_date = now()->addDays(30)->format('Y-m-d');
            } else {
                $this->auto_disable = false;
                $this->auto_disable_date = $this->ip_address = $this->interface = $this->queue_name = $this->profile = $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = $this->bandwidth = $this->service = null;
            }
            // Proceed only if service is static and router_name is set than fetch interfaces and profile
            if ($this->service == 'static' && $normalizedRouterName) {
                $this->interfaceNames = $this->readMikrotikNames(
                    $normalizedRouterName,
                    '/interface/print',
                    '/interface print without-paging terse where type="ether" or type="vlan"'
                );
                $this->profileNames = [];
                $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = null;

                return;
            } elseif ($this->service == 'pppoe' && $normalizedRouterName) {
                $this->profileNames = $this->readMikrotikNames(
                    $normalizedRouterName,
                    '/ppp/profile/print',
                    '/ppp profile print without-paging terse'
                );
                if ($this->profileNames === []) {
                    $this->profileNames = PackageList::namesForRouter($normalizedRouterName);
                }
                $this->interfaceNames = [];
                $this->ip_address = $this->queue_name = $this->caller_id = $this->bandwidth = null;

                return;
            } else {
                $this->interfaceNames = $this->profileNames = [];
                $this->auto_disable_date = $this->ip_address = $this->queue_name = $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = $this->bandwidth = $this->service = null;

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function readMikrotikNames(string $routerName, string $apiCmd, string $sshCmd): array
    {
        $names = [];
        try {
            $results = app(MikrotikController::class)->singleRead($routerName, $apiCmd, $sshCmd, [], false);
            foreach ($results as $item) {
                if (is_array($item) && isset($item['name'])) {
                    $names[] = $item['name'];
                }
            }
        } catch (\Throwable $e) {
            \Log::debug('EditCustomer MikroTik read skipped: '.$e->getMessage());
        }

        return $names;
    }

    public function rules()
    {
        return [
            'router_name' => 'required|required_with:service',
            'service' => 'nullable|required_with:router_name',
            'interface' => 'nullable|required_if:service,static',
            'ip_address' => 'nullable|required_if:service,static|ip',
            'bandwidth' => 'nullable|required_if:service,static|regex:/^\d+(M|K)\/\d+(M|K)$/',
            'caller_id' => 'nullable|mac_address',
            'queue_name' => 'nullable|required_if:service,static|string|max:25',
            'profile' => 'nullable|required_if:service,pppoe|string|max:25',
            // 'username' => 'nullable|required_if:service,pppoe|string|max:25|unique:p_p_p_secrets,username',
            'username' => [
                'nullable',
                'required_if:service,pppoe',
                'string',
                'max:25',
                function ($attribute, $value, $fail) {
                    $customer = CustomersInfo::with('pppUser')
                        ->where('customer_unique_id', decrypt($this->customerId))
                        ->first();

                    if ($customer && $customer->pppUser) {
                        // Check if the new username is different from the current one
                        $currentUsername = $customer->pppUser->username ?? null;

                        if ($currentUsername !== $value) {
                            $exists = CustomersInfo::whereHas('pppUser', function ($query) use ($value) {
                                $query->where('username', $value);
                            })->exists();

                            if ($exists) {
                                $fail("The username '{$value}' is already taken within PPP users.");
                            }
                        }
                    }
                },
            ],
        ];
    }

    public function savePPPUser()
    {
        if (auth()->user()->hasRole('Reseller')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $this->validate();

            if ($this->service == 'pppoe') {
                try {
                    $pppUserCheck = PPPSecrets::where('router_name', $this->router_name)->where('username', $this->username)->first();

                    if ($pppUserCheck) {
                        // Check if the same PPP user ID is already linked to another customer
                        $isUsed = CustomersInfo::where('ppp_user_id', $pppUserCheck->id);

                        if ($isUsed->exists()) {
                            flash()->error('This PPP User ID is already assigned to another customer.'.$isUsed->first()->customer_name.' ('.$isUsed->first()->customer_unique_id.') !');

                            return;
                        }
                    }

                    // Build and execute PPP secret add via pooled/cached controller
                    if ($this->ppp_remote_ip != '') {
                        $cmd = "/ppp secret add name=\"{$this->username}\" password=\"{$this->password}\" service=\"{$this->service}\" profile=\"{$this->profile}\" comment=\"{$this->comment}\" remote-address=\"{$this->ppp_remote_ip}\" caller-id=\"{$this->caller_id}\"";
                    } else {
                        $cmd = "/ppp secret add name=\"{$this->username}\" password=\"{$this->password}\" service=\"{$this->service}\" profile=\"{$this->profile}\" comment=\"{$this->comment}\" caller-id=\"{$this->caller_id}\"";
                    }

                    app(MikrotikController::class)->singleWrite($this->router_name, $cmd);
                } catch (\Throwable $e) {
                    \Log::debug('EditCustomer MikroTik PPP write skipped: '.$e->getMessage());
                }

                try {
                    $customerId = decrypt($this->customerId);
                } catch (\Exception $e) {
                    flash()->error('Invalid Customer ID!');

                    return;
                }

                $pppUser = PPPSecrets::firstOrCreate(
                    ['router_name' => $this->router_name, 'username' => $this->username],
                    [
                        'password' => $this->password,
                        'service' => $this->service,
                        'profile' => $this->profile,
                        'comment' => $this->comment,
                        'caller_id' => $this->caller_id,
                        'status' => 'active',
                        'ppp_remote_ip' => ! empty($this->ppp_remote_ip) ? $this->ppp_remote_ip : $this->ip_address,
                    ]
                );

                BillingInfo::where('customer_bill_unique_id', $customerId)->update([
                    'auto_disable_date' => $this->auto_disable_date ? Carbon::parse($this->auto_disable_date) : Carbon::now()->addDays(30),
                ]);

                if ($pppUser->exists) {
                    CustomersInfo::where('customer_unique_id', $customerId)->update([
                        'status' => 'active',
                        'ppp_user_id' => $pppUser->id,
                    ]);
                }

                flash()->success('Customer PPP User created successfully!');
                $this->loadCustomerData($this->customerId);
                $this->resetPPPUser();
            } elseif ($this->service == 'static') {
                try {
                    app(MikrotikController::class)->singleWrite(
                        $this->router_name,
                        "/queue simple add name=\"{$this->queue_name}\" profile=\"{$this->profile}\" address=\"{$this->ip_address}\" max-limit=\"{$this->bandwidth}\" comment=\"{$this->comment}\" disabled=yes"
                    );
                } catch (\Throwable $e) {
                    \Log::debug('EditCustomer MikroTik queue write skipped: '.$e->getMessage());
                }

                    $pppUser = new PPPSecrets;
                    $pppUser->router_name = $this->router_name;
                    $pppUser->username = ($this->username != '') ? $this->username : $this->queue_name;
                    $pppUser->password = $this->password;
                    $pppUser->service = $this->service;
                    $pppUser->profile = ($this->profile != '') ? $this->profile : $this->interface;
                    $pppUser->bandwidth = $this->bandwidth;
                    $pppUser->comment = $this->comment;
                    $pppUser->caller_id = $this->caller_id;
                    $pppUser->ppp_remote_ip = ($this->ppp_remote_ip != '') ? $this->ppp_remote_ip : $this->ip_address;
                    $pppUser->save();
                    CustomersInfo::where('customer_unique_id', decrypt($this->customerId))->update([
                        'status' => 'active',
                    ]);
                    flash()->success('Customer PPP User created successfully!');
                    $this->loadCustomerData($this->customerId);
                    $this->resetPPPUser();
            }
        } catch (ValidationException $e) {
            // Validation failed, extract error messages
            $errors = $e->validator->errors()->all();

            // Loop through the errors and dispatch each as a toast notification
            foreach ($errors as $error) {
                flash()->error($error);
            }

            // Re-throw the validation exception to allow @error directive to work
            throw $e;
        } catch (\Exception $e) {
            // Handle any other type of exception
            flash()->error('Error: '.$e->getMessage());
        }
    }

    public function removePhoto()
    {
        $this->photo_url = null;
        flash()->warning('Image Removed successfully!');
    }

    public function deletePhoto()
    {
        $photoUrl = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))->first();
        if ($photoUrl->photo_url && file_exists(public_path($photoUrl->photo_url))) {
            unlink(public_path($photoUrl->photo_url));
        }
        $photoUrl->update([
            'photo_url' => null,
        ]);
        $this->fields['customer']['photo_url'] = null;
        flash()->warning('Image Removed successfully!');
    }

    public function savePhoto()
    {
        // Generate a unique filename and define the path
        $filename = uniqid().'.jpg';
        $path = 'customer-images/'.$filename;

        if ($this->photo_url) {
            $image_file = $this->photo_url->getRealPath();
            // create new manager instance with desired driver
            $manager = new ImageManager(new Driver);
            // read image from file system
            $image = $manager->read($image_file);
            // Image resize
            $image->resize(300, 300);
            // save modified image in new format
            $image->save(public_path("$path"));

            $photoUrl = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))->update([
                'photo_url' => $path,
            ]);
            flash()->success('Image uploaded successfully!');
        }
    }

    public function startEditing($field)
    {
        if (str_contains($field, 'profile') || str_contains($field, 'package_name')) {
            $this->loadInterfaceNames();
        }

        // Store the current value of the field being edited in tempFields
        data_set($this->tempFields, $field, data_get($this->fields, $field));
    }

    public function cancelEditing($field)
    {
        // Restore the value of the field from tempFields if editing is canceled
        if (data_get($this->tempFields, $field) !== null) {
            data_set($this->fields, $field, data_get($this->tempFields, $field));
            unset($this->tempFields[$field]); // Clear temp value after cancel
        }
    }

    public function checkboxUpdated($propertyName)
    {
        if (str_contains($propertyName, '.')) {
            // Save the updated checkbox state to the database
            $segments = explode('.', $propertyName);
            if (count($segments) === 3 && $segments[0] === 'fields') {
                $group = $segments[1];
                $field = $segments[2];
                if ($group === 'pppUser') {
                    $dataUpdate = BillingInfo::where('customer_bill_unique_id', decrypt($this->customerId))->update([$field => $this->fields[$group][$field]]);
                } elseif ($group === 'official') {
                    $dataUpdate = OfficialInfo::where('customer_office_unique_id', decrypt($this->customerId))->update([$field => $this->fields[$group][$field]]);
                }
                if ($dataUpdate) {
                    flash()->success('Data updated successfully!');
                }
            }
        }
    }

    protected $messages = [
        'mobile.regex' => 'Mobile number must start with "880" and be 11 digits long',
        'alternative_mobile.regex' => 'Mobile number must start with "880" and be 11 digits long',
        'identification_no.regex' => 'NID No must e number and it less than 9 or grater than 17 digit',
        'password.required' => 'Password is required. No Blank or Null Value Allowed.',
    ];

    public function updateCustomer($field, $value)
    {
        if (auth()->user()->hasRole('Reseller') && ($field === 'status' || str_ends_with($field, '.status')) && $value === 'free') {
            flash()->error('Unauthorized to set status to free.');

            return;
        }

        // Define validation rules directly if there's an issue with accessing the rules method
        $rules = [
            'customer_name' => 'required|min:3|max:255',
            'mobile' => ['required', 'string', new ValidPhoneDigits],
            'email' => 'nullable|email',
            'alternative_mobile' => ['nullable', 'string', new ValidPhoneDigits],
            'identification_no' => 'nullable|regex:/^\d{9,17}$/',
            'router_name' => 'nullable|required_with:service',
            'service' => 'nullable|required_with:router_name',
            'interface' => 'nullable|required_if:service,static',
            'ip_address' => 'nullable|required_if:service,static|ip',
            'bandwidth' => 'nullable|required_if:service,static|regex:/^\d+(M|K)\/\d+(M|K)$/',
            'caller_id' => 'nullable|mac_address',
            'queue_name' => 'nullable|required_if:service,static|string|max:25',
            'profile' => 'nullable|required_if:service,pppoe|string|max:25',
            'password' => ($this->service === 'pppoe') ? 'required|string|min:3|max:25' : 'nullable|string|min:3|max:25',
            'username' => [
                'nullable',
                'required_if:service,pppoe',
                'string',
                'max:25',
                function ($attribute, $value, $fail) {
                    $customer = CustomersInfo::with('pppUser')
                        ->where('customer_unique_id', decrypt($this->customerId))
                        ->first();

                    if ($customer && $customer->pppUser) {
                        // Check if the new username is different from the current one
                        $currentUsername = $customer->pppUser->username ?? null;

                        if ($currentUsername !== $value) {
                            $exists = CustomersInfo::whereHas('pppUser', function ($query) use ($value) {
                                $query->where('username', $value);
                            })->exists();

                            if ($exists) {
                                $fail("The username '{$value}' is already taken within PPP users.");
                            }
                        }
                    }
                },
            ],
            'monthly_rent' => 'required|numeric',
            'connected_by' => 'required',
            'billing_type' => 'required',
            'connection_type' => 'required',
            'connectivity_type' => 'required',
            'status' => 'required',
            'auto_disable_month' => 'required',
            'auto_disable_date' => 'required|date',
        ];

        // Add dynamic address rules if they exist
        if ($this->addressFields) {
            foreach ($this->addressFields as $addressField) {
                if ($addressField->required == true) {
                    $rules[$addressField->label] = 'required|string|min:2';
                }
            }
        }
        // Proceed with updating customer data if validation passes
        $customer = CustomersInfo::where('customer_unique_id', decrypt($this->customerId))
            ->with('billing', 'customerAddress', 'official', 'pppUser')
            ->first();

        // Validate the specific field being updated
        $validation = Validator::make([], []); // initialize to prevent undefined variable error
        if (str_contains($field, '.')) {
            [$relation, $attribute] = explode('.', $field, 2);
            // status lives on customers_info directly, not on official relation
            if ($attribute === 'status') {
                $validation = Validator::make(['status' => $value], ['status' => 'required'], $this->messages);
            } elseif ($relation && $customer->$relation) {
                $validation = Validator::make([$attribute => $value], [
                    $attribute => $rules[$attribute] ?? 'nullable', // Apply the rule if it exists, otherwise allow nullable
                ], $this->messages);
            }
        } else {
            $validation = Validator::make([$field => $value], [
                $field => $rules[$field] ?? 'nullable', // Apply the rule if it exists, otherwise allow nullable
            ], $this->messages);
        }

        if ($validation->fails()) {
            // If validation fails, show error message
            flash()->error($validation->errors()->first());

            return;
        }

        if ($customer) {
            if (auth()->user()->hasRole('Reseller')) {
                $reseller = auth()->user()->reseller;
                if (! $reseller || $customer->reseller_id !== $reseller->id) {
                    abort(403, 'Unauthorized action.');
                }
            }
            if (str_contains($field, '.')) {
                [$relation, $attribute] = explode('.', $field, 2);
                if ($relation === 'official' && $attribute === 'reseller_id') {
                    if (! hasAccess(['Super Admin'], ['create-reseller'])) {
                        abort(403, 'Unauthorized action.');
                    }
                    $customer->reseller_id = !empty($value) ? $value : null;
                    $customer->save();
                    
                    $this->loadCustomerData($this->customerId);
                    $this->loadPackageLists($customer);
                    
                    flash()->success('Reseller updated successfully!');
                    return;
                }
                if ($relation == 'customerAddress') {
                    $addressField = AddressField::where('label', $attribute)->select('input_type')->get();
                    $addressUpdateCreate = CustomersAddress::where('customer_address_unique_id', decrypt($this->customerId))->where('label_name', $attribute)->first();
                    if ($addressUpdateCreate) {
                        $addressUpdateCreate->update([
                            'input_type_'.$addressField->first()->input_type => $value,
                        ]);
                    } else {
                        CustomersAddress::create([
                            'customer_address_unique_id' => decrypt($this->customerId),
                            'label_name' => $attribute,
                            'input_type_'.$addressField->first()->input_type => $value,
                        ]);
                    }
                    flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                    data_set($this->fields, $field, $value);
                } elseif ($relation == 'pppUser' && ($attribute == 'connection_date' || $attribute == 'package_name')) {
                    if ($attribute == 'package_name') {
                        $pkg = $this->findPackageForCustomer($customer, $value);
                        $customer->package_id = $pkg?->id;
                        if ($pkg && $customer->billing) {
                            $customer->billing->monthly_rent = $pkg->price;
                            $this->recalculateBillingTotal($customer->billing);
                            data_set($this->fields, 'billing.monthly_rent', $customer->billing->monthly_rent);
                            data_set($this->fields, 'billing.total_amount', $customer->billing->total_amount);
                        }
                        $customer->save();
                        data_set($this->fields, $field, $value);
                        $this->quickPackage = $value;
                    } else {
                        $customer->connection_date = Carbon::parse($value)->format('Y-m-d');
                        $customer->save();
                        data_set($this->fields, $field, Carbon::parse($value)->format('Y-m-d'));
                    }
                    flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                } elseif ($relation == 'pppUser' && ($attribute == 'auto_disable_date' || $attribute == 'auto_disable_month')) {
                    $customer->billing->$attribute = ($attribute == 'auto_disable_date') ? date('Y-m-d', strtotime($value)) : (($value != '') ? $value : null);
                    $customer->billing->save();

                    data_set($this->fields, $field, $value);
                    flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                } elseif ($relation == 'pppUser' && $attribute == 'username' || $attribute == 'password' || $attribute == 'service' || $attribute == 'profile' || $attribute == 'caller_id' || $attribute == 'comment' || $attribute == 'ppp_remote_ip' || $attribute == 'bandwidth' || $attribute == 'queue_name' || $attribute == 'router_name' || $attribute == 'interface' || $attribute == 'ip_address') {
                    if ($attribute == 'username') {
                        $attributeField = 'name';
                    } elseif ($attribute == 'caller_id') {
                        $attributeField = 'caller-id';
                    } else {
                        $attributeField = $attribute;
                    }

                    try {
                        app(MikrotikController::class)->updatePPPSecret($customer->pppUser->router_name, $customer->pppUser->username, $attributeField, $value);

                        $relatedModel = $customer->$relation;
                        if ($relatedModel && $relatedModel->isFillable($attribute)) {
                            $relatedModel->$attribute = $value;
                            $relatedModel->save();
                            data_set($this->fields, $field, $value);
                            flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                        } else {
                            flash()->error('Field not found or not fillable on the related model.');
                        }
                    } catch (\Exception $e) {
                        \Log::error('Failed to update PPP Secret '.$attributeField.' on router: '.$e->getMessage());
                        flash()->error('Failed to update on Mikrotik: '.$e->getMessage());
                    }
                } elseif ($relation == 'official' && $attribute == 'status') {
                    try {
                        \DB::beginTransaction();

                        if ($customer->ppp_user_id != null && $customer->pppUser) {
                            if ($value == 'active') {
                                app(MikrotikController::class)->enablePPPSecret(decrypt($this->customerId), $customer->pppUser->router_name, $customer->pppUser->username);

                                app(MikrotikController::class)->updatePPPSecret(
                                    $customer->pppUser->router_name,
                                    $customer->pppUser->username,
                                    'profile',
                                    $customer->pppUser->profile
                                );

                                try {
                                    app(MikrotikController::class)->singleWrite(
                                        $customer->pppUser->router_name,
                                        '/ppp active remove [find name="'.$customer->pppUser->username.'"]'
                                    );
                                } catch (\Exception $e) {
                                    \Log::debug('EditCustomer enable active session removal skipped: '.$e->getMessage());
                                }

                                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);
                            } elseif ($value == 'disable') {
                                app(MikrotikController::class)->disablePPPSecret(decrypt($this->customerId), $customer->pppUser->router_name, $customer->pppUser->username);
                                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'disable']);
                            }
                        }

                        $customer->$attribute = $value;
                        $customer->save();
                        data_set($this->fields, $field, $value);
                        $this->quickStatus = $value;

                        \DB::commit();
                        flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        \Log::error('Failed to update status for customer '.$customer->customer_unique_id.': '.$e->getMessage());
                        flash()->error('Failed to update status on router: '.$e->getMessage());
                    }
                } elseif ($relation && $customer->$relation) {
                    $relatedModel = $customer->$relation;

                    if ($relatedModel && $relatedModel->isFillable($attribute)) {
                        if ($attribute == 'billing_type') {
                            $customer->billing->billing_type = $value;
                            $customer->billing->save();
                        }
                        $relatedModel->$attribute = $value;
                        $relatedModel->save();
                        if ($relation === 'billing' && in_array($attribute, ['monthly_rent', 'additional_charge', 'discount', 'advance', 'previous_due', 'vat'], true)) {
                            $this->recalculateBillingTotal($customer->billing);
                            data_set($this->fields, 'billing.total_amount', $customer->billing->total_amount);
                        }
                        if ($attribute === 'connected_by') {
                            $userName = $this->userLists->where('id', $value)->first()->name ?? '';
                            data_set($this->fields, $field, $userName);
                        } elseif ($attribute === 'customer_type') {
                            data_set($this->fields, $field, $value);
                            $this->quickCustomerType = $value;
                        } else {
                            data_set($this->fields, $field, $value);
                        }
                        flash()->success(ucwords(str_replace('_', ' ', $attribute)).' updated successfully!');
                    } else {
                        flash()->error('Field not found or not fillable on the related model.');
                    }
                } else {
                    flash()->error('Related model not found or not initialized.');
                }
            } else {
                $customer->$field = $value;
                $customer->save();
                data_set($this->fields['customer'], $field, $value); // Update the specific field in the 'customer'

                flash()->success(ucwords(str_replace('_', ' ', $field)).' updated successfully!');
            }
        } else {
            flash()->error('Customer not found.');
        }
    }

    protected function recalculateBillingTotal(BillingInfo $billing): void
    {
        $subtotal = (float) $billing->monthly_rent + (float) $billing->previous_due + (float) $billing->additional_charge;
        $vatAmount = ($subtotal * (float) $billing->vat) / 100;
        $billing->total_amount = round($subtotal + $vatAmount - ((float) $billing->advance + (float) $billing->discount), 2);
        $billing->save();
    }

    public function render()
    {
        $view = view('livewire.edit-customer');

        return $this->embedded ? $view : $view->layout('layouts.app');
    }
}
