<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\AddressField;
use App\Models\BillingInfo;
use App\Models\CustomerOnu;
use App\Models\CustomersAddress;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Models\User;
// MikrotikSSHService removed — all router I/O routed through MikrotikController (pooled + cached)
use App\Rules\ValidPhoneDigits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;

class NewCustomer extends Component
{
    use WithFileUploads;

    public $customer_name;

    public $contact_person;

    public $parents_name;

    public $spouse_name;

    public $email;

    public $identification_no;

    public $mobile;

    public $alternative_mobile;

    public $profession;

    public $status = 'active';

    public $customer_code;

    public $flat_address;

    public $gps_lat;

    public $gps_lng;

    public $connection_date;

    public $service;

    public $profile;

    public $ip_address;

    public $ppp_remote_ip;

    public $username;

    public $password;

    public $queue_name;

    public $caller_id;

    public $comment;

    public $interface;

    public $bandwidth;

    public $package_name;

    public $monthly_rent;

    public $due_amount;

    public $additional_charge;

    public $discount;

    public $advance;

    public $vat;

    public $total_amount;

    public $client_type;

    public $distribution_location;

    public $description;

    public $note;

    public $connected_by;

    public $security_deposit;

    public $service_charge;

    public $customer_type = 'standard';

    public $bill_create = true;

    public $bill_sms = true;

    public $bill_email = false;

    public $continue_bill = true;

    public $first_bill_cycle = 'this_month';

    public $billing_day;

    public $grace_period_days = 0;

    public $apply_line_charges = true;

    public $line_status = 'active';

    public $onu_ownership = 'company';

    public $box_name;

    public $epon_port;

    public $onu_mac;

    public $cable_length_m;

    public $drop_direction;

    public $drop_cable_color;

    public $auto_disable;

    public $auto_disable_date;

    public $router_name = '';

    public $auto_disable_month = 0;

    public $step = 1;

    public $billing_type = 'prepaid';

    public $connection_type = 'fiber';

    public $connectivity_type = 'shared';

    public $address = []; // Initialize as an array

    public $data = [];

    public $interfaceNames = []; // Make sure this is an array for Livewire updates

    public $profileNames = []; // Make sure this is an array for Livewire updates

    public $photo_url;

    // for on load
    public $addressFields;

    public $routers;

    public $packages;

    public $users; // Declare the property to hold address fields

    public function mount()
    {
        if (! auth()->user()->can('create-customer') && ! auth()->user()->hasRole('Reseller')) {
            abort(403, 'Unauthorized action.');
        }

        $this->connected_by = auth()->id();
        $this->connection_date = now()->format('Y-m-d');
        $this->billing_day = min(28, (int) now()->format('d'));
        $this->grace_period_days = (int) (siteUrlSettings('disable_check_days') ?? 0);
        $this->auto_disable = true;
        $this->auto_disable_date = $this->expireDateFromBillingDay($this->billing_day);
        $this->status = auth()->user()->hasRole('Reseller') ? 'pending' : 'active';
        $this->customer_code = $this->nextCustomerId();
        $this->gps_lat = $this->gps_lat ?: '23.8103';
        $this->gps_lng = $this->gps_lng ?: '90.4125';

        return true;
    }

    public function nextCustomerId(): string
    {
        return app(\App\Services\Billing\CustomerIdAllocator::class)->next();
    }

    public function updatedBillingDay($value): void
    {
        $day = max(1, min(28, (int) $value));
        $this->billing_day = $day;
        $this->auto_disable_date = $this->expireDateFromBillingDay($day);
    }

    protected function expireDateFromBillingDay(?int $day): string
    {
        $day = max(1, min(28, (int) ($day ?: now()->day)));
        $candidate = now()->day($day)->startOfDay();
        if ($candidate->lt(now()->startOfDay())) {
            $candidate->addMonthNoOverflow();
        }

        return $candidate->format('Y-m-d');
    }

    public function updatedMobile($value): void
    {
        if ($this->service === 'pppoe' && blank($this->username) && filled($value)) {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
            if ($digits !== '') {
                $this->username = $digits;
            }
        }
    }

    public function rules()
    {
        // Start with the base rules
        $rules = [
            'customer_name' => 'required|min:3|max:255',
            'mobile' => ['required', 'string', new ValidPhoneDigits],
            'email' => 'nullable|email',
            'alternative_mobile' => ['nullable', 'string', new ValidPhoneDigits],
            'identification_no' => 'nullable|min:9|max:17',
            'router_name' => 'nullable|required_with:service',
            'service' => 'nullable|required_with:router_name',
            'interface' => 'nullable|required_if:service,static',
            'ip_address' => 'nullable|required_if:service,static|ip',
            'bandwidth' => 'nullable|required_if:service,static|regex:/^\d+(M|K)\/\d+(M|K)$/',
            'caller_id' => 'nullable|mac_address',
            'queue_name' => 'nullable|required_if:service,static|string|max:25',
            'profile' => 'nullable|required_if:service,pppoe|string|max:25',
            'username' => 'nullable|required_if:service,pppoe|string|max:25',
            'monthly_rent' => 'required|numeric',
            'connected_by' => 'required',
            'billing_type' => 'required',
            'connection_type' => 'required',
            'connectivity_type' => 'required',
            'photo_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'customer_code' => 'required|string|max:40|unique:customers_infos,customer_unique_id',
            'gps_lat' => 'nullable|numeric',
            'gps_lng' => 'nullable|numeric',
        ];

        // Add dynamic address rules if they exist
        if ($this->addressFields) {
            // dd($this->addressFields);
            foreach ($this->addressFields as $addressField) {
                if ($addressField->required == true) {
                    // Create rules for each required address field
                    $rules['address.'.$addressField->label] = 'required|string|max:255';
                }
            }
        }

        return $rules; // Return the combined rules
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function packageName($value)
    {
        $this->package_name = $this->profile;
        $this->calculateTotal('package_name');
    }

    public function calculateTotal($value)
    {
        // If package name is set, get the package price
        if ($this->package_name) {
            if (auth()->user()->hasRole('Reseller')) {
                $reseller = auth()->user()->reseller;
                $package = PackageList::where('package', $this->package_name)
                    ->where(function ($q) use ($reseller) {
                        $q->whereIn('id', $reseller->assignedPackages->pluck('id'))
                            ->orWhere('reseller_id', $reseller->id);
                    })->first();
            } else {
                $package = PackageList::where('package', $this->package_name)
                    ->where('router_name', ! empty($this->router_name) ? $this->router_name : null)
                    ->first();
            }
            $this->monthly_rent = $package?->price ?? '';
        }

        // Prevent modifying 'monthly_rent' if 'package_name' is set and warn the user
        if ($value == 'monthly_rent' && $this->package_name) {
            $this->addError('monthly_rent', 'First unset Package Name before changing Monthly Rent.');

            return;
        }

        // Recalculate total if any of the relevant fields change
        if (in_array($value, ['monthly_rent', 'due_amount', 'additional_charge', 'discount', 'advance', 'vat']) || $value == 'package_name') {
            $subtotal = (float) $this->monthly_rent + (float) $this->due_amount + (float) $this->additional_charge;
            $vatAmount = ($subtotal * (float) $this->vat) / 100;
            $this->total_amount = $subtotal + $vatAmount - ((float) $this->advance + (float) $this->discount);
        }
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
            if ($this->router_name) {
                $this->auto_disable = true;
                $this->auto_disable_date = $this->expireDateFromBillingDay($this->billing_day);
            } else {
                $this->auto_disable = false;
                $this->auto_disable_date = $this->ip_address = $this->interface = $this->queue_name = $this->profile = $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = $this->bandwidth = $this->service = null;
            }
            // Proceed only if service is static and router_name is set than fetch interfaces and profile
            if ($this->service == 'static' && $this->router_name) {
                $this->interfaceNames = $this->readMikrotikNames(
                    $this->router_name,
                    '/interface/print',
                    '/interface print without-paging terse where type="ether" or type="vlan"'
                );
                $this->profileNames = [];
                $this->username = $this->password = $this->ppp_remote_ip = $this->caller_id = null;

                return;
            } elseif ($this->service == 'pppoe' && $this->router_name) {
                $this->profileNames = $this->readMikrotikNames(
                    $this->router_name,
                    '/ppp/profile/print',
                    '/ppp profile print without-paging terse'
                );
                if ($this->profileNames === []) {
                    $this->profileNames = PackageList::namesForRouter($this->router_name);
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
            \Log::debug('NewCustomer MikroTik read skipped: '.$e->getMessage());
        }

        return $names;
    }

    protected function pushNewCustomerToRouter(): void
    {
        if (! $this->router_name) {
            return;
        }

        $disabled = $this->line_status === 'suspended' ? 'yes' : 'no';
        try {
            if ($this->service == 'pppoe') {
                if ($this->ppp_remote_ip != '') {
                    $cmd = "/ppp secret add name=\"{$this->username}\" password=\"{$this->password}\" service=\"{$this->service}\" profile=\"{$this->profile}\" comment=\"{$this->comment}\" remote-address=\"{$this->ppp_remote_ip}\" caller-id=\"{$this->caller_id}\" disabled={$disabled}";
                } else {
                    $cmd = "/ppp secret add name=\"{$this->username}\" password=\"{$this->password}\" service=\"{$this->service}\" profile=\"{$this->profile}\" comment=\"{$this->comment}\" caller-id=\"{$this->caller_id}\" disabled={$disabled}";
                }
                app(MikrotikController::class)->singleWrite($this->router_name, $cmd);
            } elseif ($this->service == 'static') {
                app(MikrotikController::class)->singleWrite(
                    $this->router_name,
                    "/queue simple add name=\"{$this->queue_name}\" profile=\"{$this->profile}\" address=\"{$this->ip_address}\" max-limit=\"{$this->bandwidth}\" comment=\"{$this->comment}\" disabled={$disabled}"
                );
            }
        } catch (\Throwable $e) {
            \Log::debug('NewCustomer MikroTik write skipped: '.$e->getMessage());
        }
    }

    public function removePhoto()
    {
        $this->photo_url = null;
        flash()->warning('Image Removed successfully!');
    }

    public function validateStepAndGo($targetStep)
    {
        // If user wants to go backward, just let them go
        if ($targetStep < $this->step) {
            $this->step = $targetStep;
            return;
        }

        // Validate step-by-step with a single validate pass
        try {
            $allRules = $this->rules();
            $stepRules = [];

            if ($this->step === 1) {
                // Collect rules for Step 1
                $stepRules['customer_name'] = $allRules['customer_name'];
                $stepRules['mobile'] = $allRules['mobile'];
                if ($this->email) {
                    $stepRules['email'] = $allRules['email'];
                }
                if ($this->alternative_mobile) {
                    $stepRules['alternative_mobile'] = $allRules['alternative_mobile'];
                }
                if ($this->identification_no) {
                    $stepRules['identification_no'] = $allRules['identification_no'];
                }
                
                $this->validate($stepRules);

            } elseif ($this->step === 2) {
                // Collect rules for Step 2 (Address)
                if ($this->addressFields) {
                    foreach ($this->addressFields as $addressField) {
                        if ($addressField->required == true) {
                            $fieldName = 'address.' . $addressField->label;
                            if (isset($allRules[$fieldName])) {
                                $stepRules[$fieldName] = $allRules[$fieldName];
                            }
                        }
                    }
                }
                if (!empty($stepRules)) {
                    $this->validate($stepRules);
                }

            } elseif ($this->step === 3 && !auth()->user()->hasRole('Reseller')) {
                // Collect rules for Step 3 (Network Config)
                if ($this->router_name || $this->service) {
                    $stepRules['router_name'] = $allRules['router_name'];
                    $stepRules['service'] = $allRules['service'];
                    
                    if ($this->service === 'static') {
                        $stepRules['interface'] = $allRules['interface'] ?? 'required';
                        $stepRules['ip_address'] = $allRules['ip_address'] ?? 'required|ip';
                        $stepRules['bandwidth'] = $allRules['bandwidth'] ?? 'required';
                        $stepRules['queue_name'] = $allRules['queue_name'] ?? 'required';
                    } elseif ($this->service === 'pppoe') {
                        $stepRules['profile'] = $allRules['profile'] ?? 'required';
                        $stepRules['username'] = $allRules['username'] ?? 'required';
                    }
                }
                if ($this->caller_id) {
                    $stepRules['caller_id'] = $allRules['caller_id'];
                }
                
                if (!empty($stepRules)) {
                    $this->validate($stepRules);
                }

            } elseif ($this->step === (!auth()->user()->hasRole('Reseller') ? 4 : 3)) {
                // Collect rules for Step 4 (Billing Info)
                $stepRules['package_name'] = 'required';
                $stepRules['monthly_rent'] = 'required|numeric';
                
                $this->validate($stepRules);
            }

            // If we successfully validated the current step's rules, check if intermediate steps are also valid
            if ($targetStep > $this->step + 1) {
                $targetRules = [];
                for ($s = $this->step + 1; $s < $targetStep; $s++) {
                    if ($s === 2) {
                        if ($this->addressFields) {
                            foreach ($this->addressFields as $addressField) {
                                if ($addressField->required == true) {
                                    $targetRules['address.' . $addressField->label] = 'required|string|max:255';
                                }
                            }
                        }
                    } elseif ($s === 3 && !auth()->user()->hasRole('Reseller')) {
                        if ($this->router_name || $this->service) {
                            $targetRules['router_name'] = 'required';
                            $targetRules['service'] = 'required';
                            if ($this->service === 'static') {
                                $targetRules['interface'] = 'required';
                                $targetRules['ip_address'] = 'required|ip';
                                $targetRules['bandwidth'] = 'required|regex:/^\d+(M|K)\/\d+(M|K)$/';
                                $targetRules['queue_name'] = 'required|string|max:25';
                            } elseif ($this->service === 'pppoe') {
                                $targetRules['profile'] = 'required|string|max:25';
                                $targetRules['username'] = 'required|string|max:25';
                            }
                        }
                    } elseif ($s === (!auth()->user()->hasRole('Reseller') ? 4 : 3)) {
                        $targetRules['package_name'] = 'required';
                        $targetRules['monthly_rent'] = 'required|numeric';
                    }
                }
                if (!empty($targetRules)) {
                    $this->validate($targetRules);
                }
            }

            $this->step = $targetStep;

        } catch (ValidationException $e) {
            // Re-throw validation exception to let Livewire display standard error messages on inputs
            throw $e;
        }
    }

    public function save()
    {
        try {
            $this->calculateTotal('package_name');
            $this->validate();

            if ($this->service == 'pppoe' || $this->service == 'static') {
                $this->pushNewCustomerToRouter();
            }
            $this->createUser();
        } catch (ValidationException $e) {
            // Validation failed, extract error messages
            $errors = $e->validator->errors()->all();

            // Loop through the errors and each as a toast notification
            foreach ($errors as $error) {
                flash()->error($error);
            }

            // Re-throw the validation exception to allow @error directive to work
            throw $e;
        } catch (\Exception $e) {
            flash()->error('Error: '.$e->getMessage());
        }
    }

    public function createUser()
    {
        // Start a database transaction
        DB::beginTransaction();

        try {
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
            }

            $pppUser = null;
            if (auth()->user()->can('mikrotik-user-create')) {
                // create ppp_user table record
                $pppUserCheck = PPPSecrets::where('username', $this->username)->orwhere('username', $this->queue_name)->first();

                // Check if this PPP user is already linked to a customer
                if ($pppUserCheck && CustomersInfo::where('ppp_user_id', $pppUserCheck->id)->exists()) {
                    $this->dispatch('customerError', 'This PPP user is already linked to a customer', 'error');
                }

                if ($pppUserCheck) {
                    $pppUser = $pppUserCheck;
                } else {
                    if ($this->router_name) {
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
                    }
                }
            }

            // create customers_info table record
            $newId = filled($this->customer_code)
                ? trim((string) $this->customer_code)
                : $this->nextCustomerId();
            if (CustomersInfo::where('customer_unique_id', $newId)->exists()) {
                throw new \RuntimeException('This Customer ID is already in use. Change Customer ID and save again.');
            }
            if (! saasAssertQuota('customers')) {
                DB::rollBack();

                return;
            }

            $customer = new CustomersInfo;
            if (\Illuminate\Support\Facades\Schema::hasColumn('customers_infos', 'saas_operator_id')) {
                $customer->saas_operator_id = \App\Services\Saas\SaasContext::operatorId();
            }
            if (auth()->user()->hasRole('Reseller')) {
                $customer->reseller_id = auth()->user()->reseller->id;
                $customer->status = 'pending';
            } else {
                $customer->status = in_array($this->status, ['active', 'disable', 'pending'], true)
                    ? $this->status
                    : 'active';
            }
            $customer->customer_unique_id = $newId;
            $customer->customer_name = $this->customer_name;
            $customer->contact_person = $this->contact_person;
            $customer->parents_name = $this->parents_name;
            $customer->spouse_name = $this->spouse_name;
            $customer->address = $this->flat_address;
            $customer->email = $this->email;
            $customer->identification_no = $this->identification_no;
            $customer->photo_url = $this->photo_url ? $path : null;
            $customer->mobile = '88'.$this->mobile;
            $customer->alternative_mobile = $this->alternative_mobile ? ('88'.$this->alternative_mobile) : null;
            $customer->profession = $this->profession;
            $customer->ppp_user_id = $pppUser?->id;
            $customer->connection_date = $this->connection_date ?: now()->format('Y-m-d');

            // Get package_id based on selected package name and router
            $assignedPackageId = null;
            if ($this->package_name) {
                if (auth()->user()->hasRole('Reseller')) {
                    $reseller = auth()->user()->reseller;
                    $pkg = PackageList::where('package', $this->package_name)
                        ->where(function ($q) use ($reseller) {
                            $q->whereIn('id', $reseller->assignedPackages->pluck('id'))
                                ->orWhere('reseller_id', $reseller->id);
                        })->first();
                    $assignedPackageId = $pkg?->id;
                } else {
                    $pkg = PackageList::where('package', $this->package_name)
                        ->where('router_name', ! empty($this->router_name) ? $this->router_name : null);
                    $assignedPackageId = $pkg->first()?->id;
                }
            }
            $customer->package_id = $assignedPackageId;

            $customer->save();

            $gpsSaved = false;
            foreach ($this->address as $key => $value) {
                // for each address field, create a new record
                $customerAddress = new CustomersAddress;
                $customerAddress->customer_address_unique_id = $customer->customer_unique_id;
                $customerAddress->label_name = $key;

                // input type should be fetched from the address field table
                $inputType = AddressField::where('label', $key)->first();
                if ($inputType) {
                    $customerInputType = 'input_type_'.$inputType->input_type;
                    $customerAddress->$customerInputType = $value;
                }
                if (! $gpsSaved && (filled($this->gps_lat) || filled($this->gps_lng))) {
                    $customerAddress->latitude = $this->gps_lat ?: null;
                    $customerAddress->longitude = $this->gps_lng ?: null;
                    $gpsSaved = true;
                }
                $customerAddress->save();
            }

            if (! $gpsSaved && (filled($this->gps_lat) || filled($this->gps_lng))) {
                $gpsAddress = new CustomersAddress;
                $gpsAddress->customer_address_unique_id = $customer->customer_unique_id;
                $gpsAddress->label_name = 'GPS';
                $gpsAddress->input_type_text = trim(($this->gps_lat ?? '').','.($this->gps_lng ?? ''), ',');
                $gpsAddress->latitude = $this->gps_lat ?: null;
                $gpsAddress->longitude = $this->gps_lng ?: null;
                $gpsAddress->save();
            }

            $installCharge = $this->apply_line_charges ? $this->normalizeValue($this->service_charge) : 0;
            $extraCharge = $this->normalizeValue($this->additional_charge) + (float) $installCharge;

            $customerBilling = new BillingInfo;
            $customerBilling->customer_bill_unique_id = $customer->customer_unique_id;
            $customerBilling->billing_type = $this->billing_type;
            $customerBilling->monthly_rent = $this->normalizeValue($this->monthly_rent);
            $customerBilling->additional_charge = $extraCharge;
            $customerBilling->discount = $this->normalizeValue($this->discount);
            $customerBilling->advance = $this->normalizeValue($this->advance);
            $customerBilling->vat = $this->normalizeValue($this->vat);
            $customerBilling->auto_disable = $this->auto_disable ?? 1;
            $customerBilling->auto_disable_date = $this->auto_disable_date ?? null;
            $customerBilling->auto_disable_month = $this->auto_disable_month;
            $customerBilling->billing_day = max(1, min(28, (int) ($this->billing_day ?: now()->day)));
            $customerBilling->grace_period_days = max(0, (int) $this->grace_period_days);

            $subtotal = (float) $customerBilling->monthly_rent + (float) $extraCharge + (float) $this->normalizeValue($this->due_amount);
            $vatAmount = ($subtotal * (float) $customerBilling->vat) / 100;
            $computedTotal = $subtotal + $vatAmount - ((float) $customerBilling->advance + (float) $customerBilling->discount);
            $customerBilling->total_amount = $computedTotal;

            // anetbd first_bill_cycle: next month → no due yet
            if ($this->first_bill_cycle === 'next_month') {
                $customerBilling->due_amount = 0;
            } else {
                $customerBilling->due_amount = $computedTotal;
            }
            $customerBilling->save();

            // create Official information table record
            $customerOfficial = new OfficialInfo;
            $customerOfficial->customer_office_unique_id = $customer->customer_unique_id;
            $customerOfficial->billing_type = $this->billing_type;
            $customerOfficial->connection_type = $this->connection_type;
            $customerOfficial->connectivity_type = $this->connectivity_type;
            $customerOfficial->client_type = $this->client_type;
            $customerOfficial->customer_type = $this->customer_type;
            $customerOfficial->distribution_location = $this->distribution_location;
            $customerOfficial->description = $this->description;
            $customerOfficial->note = trim(($this->note ?? '').($this->first_bill_cycle === 'next_month' ? ' | First bill: next month' : ''));
            $customerOfficial->service_charge = $installCharge;
            $customerOfficial->security_deposit = $this->normalizeValue($this->security_deposit);
            $customerOfficial->bill_create = (bool) $this->bill_create;
            $customerOfficial->bill_sms = (bool) $this->bill_sms;
            $customerOfficial->bill_email = (bool) $this->bill_email;
            $customerOfficial->continue_bill = (bool) $this->continue_bill;
            $customerOfficial->connected_by = $this->connected_by;
            $customerOfficial->save();

            if (filled($this->epon_port) || filled($this->onu_mac) || filled($this->box_name)) {
                CustomerOnu::create([
                    'customers_info_id' => $customer->id,
                    'pon_port' => $this->epon_port,
                    'mac_address' => $this->onu_mac,
                    'source' => 'manual',
                    'oper_status' => $this->line_status === 'active' ? 'online' : 'unknown',
                    'meta' => array_filter([
                        'onu_ownership' => $this->onu_ownership,
                        'box_name' => $this->box_name,
                        'cable_length_m' => $this->cable_length_m,
                        'drop_direction' => $this->drop_direction,
                        'drop_cable_color' => $this->drop_cable_color,
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);
            }

            // Commit transaction if all goes well
            DB::commit();

            flash()->success('Customer created successfully! ID: '.$customer->customer_unique_id);

            // Clear form
            $this->reset();

            // Redirect to customers list
            if (auth()->user()->hasRole('Reseller')) {
                return redirect()->route('reseller.customers.index');
            }

            return redirect('/customers');

        } catch (\Exception $e) {
            // Rollback transaction if any error occurs
            DB::rollBack();

            // Delete the image if it was saved
            // if (Storage::disk('public')->exists($path)) {
            //     Storage::disk('public')->delete($path);
            // }
            if (isset($path) && file_exists(public_path($path))) {
                unlink(public_path($path));
            }
            flash()->error('Error: '.$e->getMessage());
        }
    }

    private function normalizeValue($value)
    {
        return $value === '' || $value === null ? 0 : $value;
    }

    public function render()
    {
        $this->addressFields = AddressField::orderBy('order', 'asc')->get();
        $this->routers = RouterList::select('router_name')->where('action', 'connected')->get();

        if (auth()->user()->hasRole('Reseller')) {
            $reseller = auth()->user()->reseller;
            $this->packages = PackageList::where(function ($q) use ($reseller) {
                $q->whereIn('id', $reseller->assignedPackages->pluck('id'))
                    ->orWhere('reseller_id', $reseller->id);
            })
                ->select('price', 'package')
                ->get();
        } else {
            $this->packages = PackageList::select('price', 'package')
                ->when(! empty($this->router_name), fn ($q) => $q->where('router_name', $this->router_name))
                ->get();
        }

        // $users = User::permission('create-user')->get();
        $users = User::query();
        if ($operatorId = \App\Services\Saas\SaasContext::operatorId()) {
            $users->where('saas_operator_id', $operatorId);
        } elseif (\App\Services\Saas\SaasContext::isPlatformOwner()) {
            $users->whereNull('saas_operator_id');
        }
        $this->users = $users->get();

        return view('livewire.new-customer')->layout('layouts.app');
    }
}
