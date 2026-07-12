<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Services\Mikrotik\MikrotikPppImportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class MikrotikSync extends Component
{
    use WithPagination;

    public $RouterListId;

    public $router_name;

    public $ip_address;

    public $username;

    public $password;

    public $ssh_port;

    public $api_port;

    public bool $showForm = true;

    /** anetbd-style import panel */
    public bool $showImportPanel = false;

    public ?int $importRouterId = null;

    public string $importRouterName = '';

    public int $importSecretTotal = 0;

    /** @var list<string> */
    public array $selectedSecrets = [];

    public bool $createMissing = true;

    public bool $updateExisting = true;

    public string $secretSearch = '';

    /** ispbillling code formats */
    public string $codeFormat = 'prefix_sequential';

    public int $secretPage = 1;

    public int $secretPerPage = 50;

    public function mount()
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->api_port === null || $this->api_port === '') {
            $this->api_port = 8728;
        }

        // Deep-link edit: /mikrotik?edit=8 (reliable even if Livewire morph fails)
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $this->loadRouterForEdit($editId);
        }

        if (session()->has('errors')) {
            $this->showForm = true;
            $this->router_name = old('router_name', $this->router_name);
            $this->ip_address = old('ip_address', $this->ip_address);
            $this->username = old('username', $this->username);
            $this->ssh_port = old('ssh_port', $this->ssh_port);
            $this->api_port = old('api_port', $this->api_port ?: 8728);
            $this->RouterListId = old('router_id', $this->RouterListId);
        }
    }

    public function loadRouterForEdit(int $id): void
    {
        $router = RouterList::find($id);
        if (! $router) {
            flash()->error('Router not found!');

            return;
        }

        $this->showForm = true;
        $this->RouterListId = $id;
        $this->router_name = (string) ($router->router_name ?? '');
        $this->ip_address = (string) ($router->ip_address ?? '');
        $this->username = (string) ($router->username ?? '');
        $this->password = '';
        $this->ssh_port = $router->ssh_port;
        $this->api_port = $router->api_port ?: 8728;
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->reset(['RouterListId', 'router_name', 'ip_address', 'username', 'password', 'ssh_port', 'api_port']);
        }
    }

    public function render()
    {
        $routers = RouterList::query()
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function ($router) {
                $router->user_list_count = PPPSecrets::where('router_name', $router->router_name)
                    ->where('status', '!=', 'removed')
                    ->count();
                $router->online_count = PPPSecrets::where('router_name', $router->router_name)
                    ->where('status', '!=', 'removed')
                    ->whereNotNull('uptime')
                    ->count();

                return $router;
            });

        return view('livewire.mikrotik-sync', [
            'routers' => $routers,
        ])->layout('layouts.app');
    }

    private function importCacheKey(?int $routerId = null): string
    {
        $id = $routerId ?? $this->importRouterId ?? 0;

        return 'mikrotik:import_secrets:'.$id;
    }

    /**
     * @return list<array{name: string, profile: string, disabled: bool, comment: string}>
     */
    private function cachedSecrets(): array
    {
        if (! $this->importRouterId) {
            return [];
        }

        $cached = Cache::get($this->importCacheKey());

        return is_array($cached) ? $cached : [];
    }

    public function rules()
    {
        return [
            'router_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('router_lists', 'router_name')->ignore($this->RouterListId),
            ],
            'ip_address' => ['required', 'ip',
                function ($attribute, $value, $fail) {
                    $exists = RouterList::all()->filter(function ($router) use ($value) {
                        if ($this->RouterListId && (int) $router->id === (int) $this->RouterListId) {
                            return false;
                        }

                        if ($router->ip_address !== $value) {
                            return false;
                        }

                        $sshMatch = ($router->ssh_port !== null && $this->ssh_port !== null && (int) $router->ssh_port === (int) $this->ssh_port);
                        $apiMatch = ($router->api_port !== null && $this->api_port !== null && (int) $router->api_port === (int) $this->api_port);

                        return $sshMatch || $apiMatch;
                    })->isNotEmpty();
                    if ($exists) {
                        $fail('This IP address is already used with the same SSH or API port.');
                    }
                },
            ],
            'username' => 'required|string|max:255',
            'password' => [Rule::requiredIf(empty($this->RouterListId)), 'nullable', 'string', 'max:255'],
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'api_port' => 'nullable|integer|min:1|max:65535',
        ];
    }

    public function submit()
    {
        $this->ssh_port = ($this->ssh_port === '' || $this->ssh_port === null) ? null : (int) $this->ssh_port;
        $this->api_port = ($this->api_port === '' || $this->api_port === null) ? null : (int) $this->api_port;

        // Default API port when neither port provided (common UI miss)
        if ($this->ssh_port === null && $this->api_port === null) {
            $this->api_port = 8728;
        }

        try {
            $this->validate($this->rules());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $first = collect($e->validator->errors()->all())->first();
            if ($first) {
                flash()->error($first);
            }
            throw $e;
        }

        $data = [
            'router_name' => trim((string) $this->router_name),
            'ip_address' => trim((string) $this->ip_address),
            'username' => trim((string) $this->username),
            'ssh_port' => $this->ssh_port,
            'api_port' => $this->api_port,
        ];

        if (! empty($this->password)) {
            $data['password'] = $this->password;
        }

        try {
            if (! empty($this->RouterListId)) {
                $router = RouterList::find($this->RouterListId);
                if (! $router) {
                    flash()->error('Router not found!');

                    return;
                }
                $router->fill($data);
                $router->save();
                flash()->success('Router updated successfully!');
            } else {
                if (empty($data['password'])) {
                    flash()->error('Password is required.');

                    return;
                }
                $data['action'] = 'disconnected';
                RouterList::create($data);
                flash()->success('Router added successfully!');
            }
        } catch (\Throwable $e) {
            flash()->error('Could not save router: '.$e->getMessage());

            return;
        }

        $this->reset(['RouterListId', 'router_name', 'ip_address', 'username', 'password', 'ssh_port', 'api_port']);
        $this->api_port = 8728;
        $this->showForm = true;
    }

    public function cancelEdit(): void
    {
        $this->reset(['RouterListId', 'router_name', 'ip_address', 'username', 'password', 'ssh_port', 'api_port']);
        $this->api_port = 8728;
    }

    public function connect_toggle($routerId)
    {
        $router = RouterList::find($routerId);
        if (! $router) {
            flash()->error('Router not found!');

            return;
        }

        if ($router->action === 'connected') {
            $this->setConnected($routerId, false);
        } else {
            $this->setConnected($routerId, true);
        }
    }

    public function setConnected($routerId, bool $connected = true)
    {
        $router = RouterList::find($routerId);
        if (! $router) {
            flash()->error('Router not found!');

            return;
        }

        if (! $connected) {
            $router->action = 'disconnected';
            $router->save();
            flash()->success('Router '.$router->router_name.' disconnected.');

            return;
        }

        if (! $router->api_port && ! $router->ssh_port) {
            flash()->error('Set API port (or SSH port) before connecting.');

            return;
        }

        // Live probe — only mark Connected/Online when MikroTik actually answers
        $probe = app(MikrotikController::class)->checkConnection(
            (string) $router->ip_address,
            $router->ssh_port,
            $router->api_port,
            (string) $router->username,
            (string) $router->password,
            '/system/resource/print',
            '/system resource print',
            [],
            false
        );

        if (empty($probe['status'])) {
            $detail = $probe['message'] ?? 'API unreachable';
            if (! empty($probe['errors']['api'])) {
                $detail .= ' — '.$probe['errors']['api'];
            }
            flash()->error('Router offline / not reachable: '.$detail);

            return;
        }

        $router->action = 'connected';
        $router->save();

        $online = $this->refreshOnlineSessions($router->router_name);
        flash()->success("Router {$router->router_name} is Online ({$probe['type']}). PPP online: {$online}");
    }

    /**
     * Mark PPP secrets online from /ppp/active (uptime timestamp = currently online).
     */
    public function refreshOnlineSessions(string $routerName): int
    {
        try {
            $sessions = app(MikrotikController::class)->getActivePppSessions($routerName);
        } catch (\Throwable $e) {
            return 0;
        }

        if (! is_array($sessions)) {
            return 0;
        }

        $onlineNames = [];
        foreach ($sessions as $session) {
            if (! is_array($session)) {
                continue;
            }
            $name = strtolower(trim((string) ($session['name'] ?? '')));
            if ($name !== '') {
                $onlineNames[$name] = true;
            }
        }

        PPPSecrets::where('router_name', $routerName)->update(['uptime' => null]);

        if ($onlineNames === []) {
            return 0;
        }

        $ids = PPPSecrets::where('router_name', $routerName)
            ->where('status', '!=', 'removed')
            ->get(['id', 'username'])
            ->filter(fn ($s) => isset($onlineNames[strtolower((string) $s->username)]))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        PPPSecrets::whereIn('id', $ids)->update(['uptime' => now()]);

        return count($ids);
    }

    public function userSync($pppSecrets)
    {
        foreach ($pppSecrets as $routerName => $result) {
            if (! is_array($result)) {
                flash()->error("Invalid response for router {$routerName}");

                continue;
            }

            if (empty($result['status'])) {
                $msg = $result['message'] ?? 'Connection failed';
                flash()->error("Skipped synchronizing {$routerName}: {$msg}");

                continue;
            }

            $users = $result['data'] ?? [];
            if (! is_array($users)) {
                $users = [];
            }

            $createdCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;

            DB::beginTransaction();
            try {
                // 1. Mark existing users for this router as removed temporarily
                PPPSecrets::where('router_name', $routerName)
                    ->where('status', '!=', 'removed')
                    ->update(['status' => 'removed']);

                // 2. Pre-load all existing secrets for this router
                $existingSecrets = PPPSecrets::where('router_name', $routerName)
                    ->get()
                    ->keyBy(fn ($item) => strtolower($item->username));

                // 3. Pre-fetch latest customer unique ID count
                $prefix = siteUrlSettings('customer_id_prefix') ?: 'FCNET';
                $lastCustomerUniqueId = CustomersInfo::orderBy('id', 'desc')->value('customer_unique_id');
                if ($lastCustomerUniqueId) {
                    if (str_starts_with($lastCustomerUniqueId, $prefix)) {
                        $lastIdCount = (int) substr($lastCustomerUniqueId, strlen($prefix));
                    } else {
                        if (preg_match('/(\d+)$/', $lastCustomerUniqueId, $matches)) {
                            $lastIdCount = (int) $matches[1];
                        } else {
                            $lastIdCount = 99;
                        }
                    }
                } else {
                    $lastIdCount = 99;
                }

                $statusGroups = []; // For bulk status updates

                foreach ($users as $user) {
                    $username = $user['name'];
                    $rawPassword = $user['password'] ?? '';

                    $lowerUsername = strtolower($username);
                    $existingSecret = $existingSecrets->get($lowerUsername);

                    // --- REVERSIBLE ENCRYPTION LOGIC ---
                    // If no existing record: Encrypt new password
                    // If existing record:
                    //    - If the decrypted password matches the raw Mikrotik password, keep the raw original encrypted string
                    //      (to avoid updating the database since encrypting yields a new string each time).
                    //    - If the password changed on Mikrotik (or it wasn't encrypted/hashed yet), store the plaintext raw password
                    //      (the model attribute setter will automatically encrypt it).
                    $passwordToStore = $rawPassword;

                    if ($existingSecret) {
                        if ($existingSecret->password === $rawPassword) {
                            // Password unchanged, keep the existing database (encrypted) value to avoid isDirty() triggering
                            $passwordToStore = $existingSecret->getRawOriginal('password');
                        } else {
                            // Password changed, store the new plaintext value (which model setter will encrypt)
                            $passwordToStore = $rawPassword;
                        }
                    } else {
                        // New user, store plaintext (which model setter will encrypt)
                        $passwordToStore = $rawPassword;
                    }


                    try {
                        $lastLoggedOut = null;
                        if (! empty($user['last-logged-out'])) {
                            $dt = Carbon::createFromFormat('M/d/Y H:i:s', $user['last-logged-out']);
                            if ($dt->year >= 2000) {
                                $lastLoggedOut = $dt->format('Y-m-d H:i:s');
                            }
                        }
                    } catch (\Exception $e) {
                        $lastLoggedOut = null;
                    }

                    $expiredProfile = siteUrlSettings('expired_profile_name') ?? 'Expired';
                    $profileFromMikrotik = $user['profile'] ?? '-';
                    $profileToStore = ($profileFromMikrotik === $expiredProfile && $existingSecret)
                        ? $existingSecret->profile
                        : $profileFromMikrotik;

                    // Normalize status from both API (disabled = true/false) and SSH (status = active/disable)
                    $status = 'active';
                    if (isset($user['status'])) {
                        $status = $user['status'];
                    } elseif (isset($user['disabled'])) {
                        $status = ($user['disabled'] === 'true' || $user['disabled'] === true) ? 'disable' : 'active';
                    }

                    $secretData = [
                        'router_name' => $routerName,
                        'username' => $username,
                        'password' => $passwordToStore,
                        'service' => $user['service'] ?? '-',
                        'profile' => $profileToStore,
                        'caller_id' => $user['caller-id'] ?? '',
                        'comment' => $user['comment'] ?? '',
                        'ppp_remote_ip' => $user['ppp_remote_ip'] ?? '',
                        'bandwidth' => trim(($user['limit-bytes-in'] ?? '').'/'.($user['limit-bytes-out'] ?? ''), '/'),
                        'last_logged_out' => $lastLoggedOut,
                        'last_caller_id' => $user['last-caller-id'] ?? '',
                        'last_disconnect_reason' => $user['last-disconnect-reason'] ?? '',
                        'routes' => $user['routes'] ?? '',
                        'ipv6_routes' => $user['ipv6-routes'] ?? '',
                        'status' => $status,
                    ];

                    if ($existingSecret) {
                        $existingSecret->fill($secretData);
                        if ($existingSecret->isDirty()) {
                            $statusChanged = $existingSecret->isDirty('status');
                            $newStatus = $existingSecret->status;

                            $existingSecret->save();
                            $updatedCount++;

                            if ($statusChanged) {
                                $statusGroups[$newStatus][] = $existingSecret->id;
                            }
                        } else {
                            $unchangedCount++;
                            // Even if unchanged, we consider them 'active/not removed' now since they were in Mikrotik.
                            // But since we did `->update(['status' => 'removed'])` earlier on all users,
                            // we need to set the status back if it was unchanged in fill() but is now 'removed' in DB!
                            // Wait, fill() populated 'status' from Mikrotik, and if it wasn't dirty compared to PRE-fetched data,
                            // we didn't save. But the actual DB row is now 'removed'!
                            // Luckily, the $existingSecret instance hasn't re-fetched. It will think it's not dirty.
                            // However, we SHOULD save to revert the 'removed' status.
                            // To fix this cleanly: only the 'dirty' check needs to be mindful of the status change.
                            // Actually, fill() overrides whatever is currently loaded.
                            // If it matches exactly what we loaded at the start, isDirty is false.
                            // But the DB row status was changed to 'removed'. We MUST unconditionally save the status back!
                            PPPSecrets::where('id', $existingSecret->id)->update(['status' => $existingSecret->status]);
                        }
                    } else {
                        $newSecret = PPPSecrets::create($secretData);
                        $createdCount++;
                        $lastIdCount++;
                        $newId = $prefix.$lastIdCount;

                        CustomersInfo::create([
                            'customer_unique_id' => $newId,
                            'ppp_user_id' => $newSecret->id,
                            'customer_name' => $username,
                            'status' => 'pending',
                            'connection_date' => Carbon::now(),
                        ]);
                        BillingInfo::create(['customer_bill_unique_id' => $newId, 'billing_type' => 'prepaid', 'auto_disable_date' => Carbon::now()]);
                        OfficialInfo::create(['customer_office_unique_id' => $newId]);
                    }
                }

                // 4. Bulk update customer statuses
                foreach ($statusGroups as $status => $ids) {
                    CustomersInfo::whereIn('ppp_user_id', $ids)
                        ->whereNotIn('status', ['free', 'pending', 'deleted'])
                        ->update(['status' => $status]);
                }

                // 5. Cleanup
                PPPSecrets::where('router_name', $routerName)
                    ->where('status', 'removed')
                    ->where('updated_at', '<', Carbon::now()->subDays(15))
                    ->delete();

                DB::commit();

                $online = $this->refreshOnlineSessions($routerName);
                flash()->success("Router {$routerName} synchronized! Created: {$createdCount}, Updated: {$updatedCount}, Unchanged: {$unchangedCount}, Online now: {$online}");
            } catch (\Exception $e) {
                DB::rollBack();
                flash()->error('Error syncing router '.$routerName.': '.$e->getMessage());
            }
        }
    }

    public function dataSync($id)
    {
        // Full page import — more reliable than in-list Livewire modal with 500+ users
        $this->redirect(route('mikrotik.import', ['id' => (int) $id]), navigate: false);
    }

    public function openImportPanel(int $routerId): void
    {
        $router = RouterList::find($routerId);
        if (! $router) {
            flash()->error('Router is not found!');

            return;
        }

        try {
            if ($router->action !== 'connected') {
                $probe = app(MikrotikController::class)->checkConnection(
                    (string) $router->ip_address,
                    $router->ssh_port,
                    $router->api_port,
                    (string) $router->username,
                    (string) $router->password,
                    '/system/resource/print',
                    '/system resource print',
                    [],
                    false
                );
                if (empty($probe['status'])) {
                    flash()->error('Router offline — Connect first. '.($probe['message'] ?? ''));

                    return;
                }
                $router->action = 'connected';
                $router->save();
            }

            $secrets = app(MikrotikPppImportService::class)->listSecretsFromRouter($router);
            $lite = array_map(fn ($s) => [
                'name' => $s['name'],
                'profile' => $s['profile'],
                'disabled' => (bool) $s['disabled'],
                'comment' => $s['comment'],
            ], $secrets);

            // Keep full list in cache — Livewire public props cannot hold 500+ rows reliably
            Cache::put($this->importCacheKey($router->id), $lite, now()->addMinutes(30));
            Cache::put($this->importCacheKey($router->id).':full', $secrets, now()->addMinutes(30));

            $this->importRouterId = $router->id;
            $this->importRouterName = (string) $router->router_name;
            $this->importSecretTotal = count($lite);
            $this->selectedSecrets = [];
            $this->secretSearch = '';
            $this->secretPage = 1;
            $this->showImportPanel = true;
            $this->dispatch('mikrotik-import-opened');
            flash()->success($this->importSecretTotal.' PPP user(s) loaded from '.$router->router_name.'. Select users, then Import.');
        } catch (\Throwable $e) {
            flash()->error('Could not load PPP users: '.$e->getMessage());
        }
    }

    public function closeImportPanel(): void
    {
        if ($this->importRouterId) {
            Cache::forget($this->importCacheKey());
            Cache::forget($this->importCacheKey().':full');
        }
        $this->showImportPanel = false;
        $this->importRouterId = null;
        $this->importRouterName = '';
        $this->importSecretTotal = 0;
        $this->selectedSecrets = [];
        $this->secretSearch = '';
        $this->secretPage = 1;
    }

    public function updatedSecretSearch(): void
    {
        $this->secretPage = 1;
    }

    public function nextSecretPage(): void
    {
        $max = max(1, (int) ceil(count($this->filteredSecrets()) / max(1, $this->secretPerPage)));
        $this->secretPage = min($max, $this->secretPage + 1);
    }

    public function prevSecretPage(): void
    {
        $this->secretPage = max(1, $this->secretPage - 1);
    }

    public function selectAllSecrets(): void
    {
        // Select ALL filtered (not only current page) — ispbillling bulkToggleable behavior
        $this->selectedSecrets = array_values(array_unique(array_merge(
            $this->selectedSecrets,
            array_map(fn ($s) => $s['name'], $this->filteredSecrets())
        )));
    }

    public function selectPageSecrets(): void
    {
        $this->selectedSecrets = array_values(array_unique(array_merge(
            $this->selectedSecrets,
            array_map(fn ($s) => $s['name'], $this->pagedSecrets())
        )));
    }

    public function clearSelectedSecrets(): void
    {
        $this->selectedSecrets = [];
    }

    /**
     * @return list<array{name: string, profile: string, disabled: bool, comment: string}>
     */
    public function filteredSecrets(): array
    {
        $all = $this->cachedSecrets();
        $q = strtolower(trim($this->secretSearch));
        if ($q === '') {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn ($s) => str_contains(strtolower($s['name']), $q)
                || str_contains(strtolower($s['profile'] ?? ''), $q)
                || str_contains(strtolower($s['comment'] ?? ''), $q)
        ));
    }

    /**
     * @return list<array{name: string, profile: string, disabled: bool, comment: string}>
     */
    public function pagedSecrets(): array
    {
        $filtered = $this->filteredSecrets();
        $offset = max(0, ($this->secretPage - 1) * $this->secretPerPage);

        return array_slice($filtered, $offset, $this->secretPerPage);
    }

    public function importOneUser(string $username): void
    {
        $username = trim($username);
        if ($username === '') {
            flash()->warning('Username empty.');

            return;
        }

        $this->selectedSecrets = [$username];
        $this->importSelected();
    }

    public function importSelected(): void
    {
        if (! $this->importRouterId) {
            flash()->error('Open a router import panel first.');

            return;
        }

        $router = RouterList::find($this->importRouterId);
        if (! $router) {
            flash()->error('Router not found!');

            return;
        }

        if ($this->selectedSecrets === []) {
            flash()->warning('Select at least one PPP user to import.');

            return;
        }

        try {
            $full = Cache::get($this->importCacheKey().':full');
            $result = app(MikrotikPppImportService::class)->importSelectedFromRouter(
                $router,
                $this->selectedSecrets,
                [
                    'create_missing' => $this->createMissing,
                    'update_existing' => $this->updateExisting,
                    'code_format' => $this->codeFormat,
                ],
                is_array($full) ? $full : null
            );

            $online = $this->refreshOnlineSessions($router->router_name);
            $err = $result['errors'] === [] ? '' : ' Errors: '.count($result['errors']);
            flash()->success(sprintf(
                'Import finished for %s — Created: %d · Updated: %d · Skipped: %d · Online: %d%s',
                $router->router_name,
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $online,
                $err
            ));
            $this->closeImportPanel();
        } catch (\Throwable $e) {
            flash()->error('Import failed: '.$e->getMessage());
        }
    }

    public function importAllFromPanel(): void
    {
        flash()->warning('Bulk import disabled. Open Import users and select only the customers you want.');
    }

    public function allSync()
    {
        $routers = RouterList::query()->where('action', 'connected')->get();
        if ($routers->isEmpty()) {
            flash()->warning('No connected routers. Click Connect first.');

            return;
        }

        // Do NOT auto-create customers — only refresh live PPP online sessions.
        $onlineTotal = 0;
        $errors = [];

        foreach ($routers as $router) {
            try {
                $onlineTotal += $this->refreshOnlineSessions($router->router_name);
            } catch (\Throwable $e) {
                $errors[] = $router->router_name.': '.$e->getMessage();
            }
        }

        flash()->success(sprintf(
            'Online refresh finished — Online sessions: %d%s',
            $onlineTotal,
            $errors === [] ? '' : ' · Issues: '.count($errors)
        ));
    }

    public function edit($id)
    {
        // Full navigation with query — fills form reliably (no Livewire morph glitch)
        $this->redirect(route('mikrotik-sync', ['edit' => $id]), navigate: false);
    }

    public function delete($id)
    {
        $router = RouterList::find($id);
        try {
            if ($router) {
                $router->delete();
                flash()->success('Router deleted successfully!');
            } else {
                flash()->error('Router not found!');
            }
        } catch (\Exception $e) {
            flash()->error('Error deleting router: '.$e->getMessage());
        }
    }
}
