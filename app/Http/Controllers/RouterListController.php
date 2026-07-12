<?php

namespace App\Http\Controllers;

use App\Models\RouterList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $router->delete();
        flash()->success('Router deleted successfully!');

        return redirect()->route('mikrotik-sync');
    }
}
