<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ValidatesInlineForm;
use App\Models\PortalMarquee;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ManagePortalMarquee extends Page
{
    use ValidatesInlineForm;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static string $view = 'filament.pages.manage-portal-marquee';

    protected static ?string $title = 'Portal Marquee';

    protected static ?string $slug = 'portal-marquee';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editingId = null;

    public static function canAccess(): bool
    {
        return ManageCompanySetup::canAccess();
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
            'text' => '',
            'url' => '',
            'sort' => 0,
            'is_active' => true,
            'show_on_landing' => true,
            'show_on_portal' => true,
        ];
    }

    /**
     * @return Collection<int, PortalMarquee>
     */
    public function getItemsProperty(): Collection
    {
        return PortalMarquee::query()->ordered()->get();
    }

    public function save(): void
    {
        $data = $this->normalizeMarqueePayload($this->validatedFormPayload($this->rules()));

        if ($this->editingId) {
            PortalMarquee::query()->findOrFail($this->editingId)->update($data);
            $message = 'Marquee line updated';
        } else {
            PortalMarquee::query()->create($data);
            $message = 'Marquee line added';
        }

        Notification::make()->title($message)->success()->send();
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $item = PortalMarquee::query()->findOrFail($id);
        $this->editingId = $item->id;
        $this->form = [
            'text' => $item->text,
            'url' => $item->url ?? '',
            'sort' => $item->sort,
            'is_active' => $item->is_active,
            'show_on_landing' => $item->show_on_landing,
            'show_on_portal' => $item->show_on_portal,
        ];
    }

    public function delete(int $id): void
    {
        PortalMarquee::query()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        Notification::make()->title('Marquee line removed')->success()->send();
    }

    public function toggleActive(int $id): void
    {
        $item = PortalMarquee::query()->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        Notification::make()
            ->title($item->is_active ? 'Line activated' : 'Line hidden')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.text' => ['required', 'string', 'max:500'],
            'form.url' => ['nullable', 'string', 'max:2048', 'url'],
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
    private function normalizeMarqueePayload(array $validated): array
    {
        return [
            'text' => trim((string) $validated['text']),
            'url' => filled($validated['url'] ?? null) ? trim((string) $validated['url']) : null,
            'sort' => (int) ($validated['sort'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'show_on_landing' => (bool) ($validated['show_on_landing'] ?? false),
            'show_on_portal' => (bool) ($validated['show_on_portal'] ?? false),
        ];
    }
}
