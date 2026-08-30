<?php

namespace App\Http\Controllers;

use App\Models\CustomersInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RouterListController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        $routerId = $request->input('router_id');

        $validated = $request->validate([
            'router_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('router_lists', 'router_name')->ignore($routerId),
            ],
            'ip_address' => ['required', 'ip'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [Rule::requiredIf(empty($routerId)), 'nullable', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $sshPort = $validated['ssh_port'] ?? null;
        $apiPort = $validated['api_port'] ?? null;
        if ($sshPort === null && $apiPort === null) {
            $apiPort = 8728;
        }

        $data = [
            'router_name' => $validated['router_name'],
            'ip_address' => $validated['ip_address'],
            'username' => $validated['username'],
            'ssh_port' => $sshPort,
            'api_port' => $apiPort,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        if (! empty($routerId)) {
            $router = RouterList::findOrFail($routerId);
            $router->fill($data);
            $router->save();
            flash()->success('Router updated successfully!');
        } else {
            $data['action'] = 'disconnected';
            RouterList::create($data);
            flash()->success('Router added successfully!');
        }

        return redirect()->route('mikrotik-sync');
    }

    public function destroy(int $id): RedirectResponse
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }

        $router = RouterList::findOrFail($id);
        $name = (string) $router->router_name;

        $customerCount = CustomersInfo::query()
            ->whereHas('pppUser', fn ($q) => $q->where('router_name', $name))
            ->count();

        if ($customerCount > 0) {
            flash()->error(__('This MikroTik has :count customers. Delete or move those clients first from the Customer list — router delete does not remove users.', [
                'count' => $customerCount,
            ]));

            return redirect()->route('mikrotik-sync');
        }

        try {
            DB::transaction(function () use ($router, $name) {
                PPPSecrets::query()->where('router_name', $name)->delete();

                if (Schema::hasTable('package_lists')) {
                    PackageList::query()->where('router_name', $name)->update(['router_name' => null]);
                }

                $router->delete();
            });
        } catch (\Throwable $e) {
            flash()->error(__('Router could not be deleted: :message', ['message' => $e->getMessage()]));

            return redirect()->route('mikrotik-sync');
        }

        flash()->success(__('Router removed from billing. Live MikroTik users were not deleted.'));

        return redirect()->route('mikrotik-sync');
    }
}
