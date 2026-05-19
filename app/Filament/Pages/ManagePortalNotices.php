<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ValidatesInlineForm;
use App\Models\PortalNotice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ManagePortalNotices extends Page
{
    use ValidatesInlineForm;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.pages.manage-portal-notices';

    protected static ?string $title = 'Portal Notices';

    protected static ?string $slug = 'portal-notices';

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
            'title' => '',
            'body' => '',
            'sort' => 0,
            'is_active' => true,
            'show_on_landing' => true,
            'show_on_portal' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * @return Collection<int, PortalNotice>
     */
    public function getNoticesProperty(): Collection
    {
        return PortalNotice::query()->ordered()->get();
    }

    public function save(): void
    {
        $data = $this->normalizeNoticePayload($this->validatedFormPayload($this->rules()));

        if ($this->editingId) {
            PortalNotice::query()->findOrFail($this->editingId)->update($data);
            $message = 'Notice updated';
        } else {
            PortalNotice::query()->create($data);
            $message = 'Notice added';
        }

        Notification::make()->title($message)->success()->send();
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $notice = PortalNotice::query()->findOrFail($id);
        $this->editingId = $notice->id;
        $this->form = [
            'title' => $notice->title,
            'body' => $notice->body ?? '',
            'sort' => $notice->sort,
            'is_active' => $notice->is_active,
            'show_on_landing' => $notice->show_on_landing,
            'show_on_portal' => $notice->show_on_portal,
            'starts_at' => $notice->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $notice->ends_at?->format('Y-m-d\TH:i'),
        ];
    }

    public function delete(int $id): void
    {
        PortalNotice::query()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        Notification::make()->title('Notice removed')->success()->send();
    }

    public function toggleActive(int $id): void
    {
        $notice = PortalNotice::query()->findOrFail($id);
        $notice->update(['is_active' => ! $notice->is_active]);

        Notification::make()
            ->title($notice->is_active ? 'Notice published' : 'Notice hidden')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:200'],
            'form.body' => ['nullable', 'string', 'max:5000'],
            'form.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'form.is_active' => ['boolean'],
            'form.show_on_landing' => ['boolean'],
            'form.show_on_portal' => ['boolean'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeNoticePayload(array $validated): array
    {
        return [
            'title' => trim((string) $validated['title']),
            'body' => filled($validated['body'] ?? null) ? trim((string) $validated['body']) : null,
            'sort' => (int) ($validated['sort'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'show_on_landing' => (bool) ($validated['show_on_landing'] ?? false),
            'show_on_portal' => (bool) ($validated['show_on_portal'] ?? false),
            'starts_at' => filled($validated['starts_at'] ?? null) ? $validated['starts_at'] : null,
            'ends_at' => filled($validated['ends_at'] ?? null) ? $validated['ends_at'] : null,
        ];
    }
}
