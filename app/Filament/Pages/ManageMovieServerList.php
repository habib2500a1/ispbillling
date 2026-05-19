<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ValidatesInlineForm;
use App\Models\PortalMovieServer;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ManageMovieServerList extends Page
{
    use ValidatesInlineForm;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static string $view = 'filament.pages.manage-movie-servers';

    protected static ?string $navigationLabel = 'Movie server list';

    protected static ?string $title = 'Movie server list';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'movie-server-list';

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editingId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'url' => '',
            'note' => '',
            'sort' => 0,
            'is_active' => true,
            'show_on_landing' => true,
            'show_on_portal' => true,
        ];
    }

    /**
     * @return Collection<int, PortalMovieServer>
     */
    public function getServersProperty(): Collection
    {
        return PortalMovieServer::query()->ordered()->get();
    }

    public function save(): void
    {
        $data = $this->normalizeServerPayload($this->validatedFormPayload($this->rules()));

        if ($this->editingId) {
            PortalMovieServer::query()->findOrFail($this->editingId)->update($data);
            $message = 'Server updated';
        } else {
            PortalMovieServer::query()->create($data);
            $message = 'Server added';
        }

        Notification::make()->title($message)->success()->send();
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $server = PortalMovieServer::query()->findOrFail($id);
        $this->editingId = $server->id;
        $this->form = [
            'name' => $server->name,
            'url' => $server->url,
            'note' => $server->note ?? '',
            'sort' => $server->sort,
            'is_active' => $server->is_active,
            'show_on_landing' => $server->show_on_landing,
            'show_on_portal' => $server->show_on_portal,
        ];
    }

    public function delete(int $id): void
    {
        PortalMovieServer::query()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        Notification::make()->title('Server removed')->success()->send();
    }

    public function toggleActive(int $id): void
    {
        $server = PortalMovieServer::query()->findOrFail($id);
        $server->update(['is_active' => ! $server->is_active]);

        Notification::make()
            ->title($server->is_active ? 'Server activated' : 'Server hidden')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:120'],
            'form.url' => ['required', 'string', 'max:2048', 'url'],
            'form.note' => ['nullable', 'string', 'max:2000'],
            'form.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'form.is_active' => ['boolean'],
            'form.show_on_landing' => ['boolean'],
            'form.show_on_portal' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeServerPayload(array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'url' => trim((string) $validated['url']),
            'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            'sort' => (int) ($validated['sort'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'show_on_landing' => (bool) ($validated['show_on_landing'] ?? false),
            'show_on_portal' => (bool) ($validated['show_on_portal'] ?? false),
        ];
    }
}
