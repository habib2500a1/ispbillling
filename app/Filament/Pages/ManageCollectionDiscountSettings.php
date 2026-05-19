<?php

namespace App\Filament\Pages;

use App\Services\Billing\CollectionDiscountSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageCollectionDiscountSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string $view = 'filament.pages.manage-collection-discount-settings';

    protected static ?string $slug = 'collection-discount-settings';

    protected static ?string $navigationLabel = 'Collection discounts';

    protected static ?string $title = 'Collection discount settings';

    protected static ?string $navigationGroup = 'Billing';

    protected static bool $shouldRegisterNavigation = false;

    public bool $enabled = true;

    public bool $require_note_on_partial = true;

    public bool $require_note_on_discount = true;

    public bool $allow_custom_amount = true;

    public string $max_discount_bdt = '500';

    public string $max_discount_percent_of_due = '50';

    /** @var list<array{id: string, label: string, type: string, amount: string, max_bdt: string}> */
    public array $presets = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super-admin', 'isp-admin', 'admin']);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = CollectionDiscountSettings::get();
        $this->enabled = $settings['enabled'];
        $this->require_note_on_partial = $settings['require_note_on_partial'];
        $this->require_note_on_discount = $settings['require_note_on_discount'];
        $this->allow_custom_amount = $settings['allow_custom_amount'];
        $this->max_discount_bdt = (string) $settings['max_discount_bdt'];
        $this->max_discount_percent_of_due = (string) $settings['max_discount_percent_of_due'];
        $this->presets = array_map(static fn (array $p): array => [
            'id' => $p['id'],
            'label' => $p['label'],
            'type' => $p['type'],
            'amount' => (string) $p['amount'],
            'max_bdt' => isset($p['max_bdt']) ? (string) $p['max_bdt'] : '',
        ], $settings['presets']);

        if ($this->presets === []) {
            $this->addPreset();
        }
    }

    public function addPreset(): void
    {
        $n = count($this->presets) + 1;
        $this->presets[] = [
            'id' => 'preset_'.$n,
            'label' => 'Preset '.$n,
            'type' => 'fixed',
            'amount' => '50',
            'max_bdt' => '',
        ];
    }

    public function removePreset(int $index): void
    {
        unset($this->presets[$index]);
        $this->presets = array_values($this->presets);
    }

    public function save(): void
    {
        $this->validate([
            'max_discount_bdt' => 'required|numeric|min:0',
            'max_discount_percent_of_due' => 'required|numeric|min:0|max:100',
            'presets' => 'array',
            'presets.*.id' => 'required|string|max:64',
            'presets.*.label' => 'required|string|max:120',
            'presets.*.type' => 'required|in:fixed,percent',
            'presets.*.amount' => 'required|numeric|min:0.01',
            'presets.*.max_bdt' => 'nullable|numeric|min:0',
        ]);

        $presets = [];
        foreach ($this->presets as $row) {
            $preset = [
                'id' => preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($row['id']))) ?: 'preset',
                'label' => trim($row['label']),
                'type' => $row['type'],
                'amount' => (float) $row['amount'],
            ];
            if ($row['type'] === 'percent' && is_numeric($row['max_bdt']) && (float) $row['max_bdt'] > 0) {
                $preset['max_bdt'] = (float) $row['max_bdt'];
            }
            $presets[] = $preset;
        }

        CollectionDiscountSettings::save([
            'enabled' => $this->enabled,
            'require_note_on_partial' => $this->require_note_on_partial,
            'require_note_on_discount' => $this->require_note_on_discount,
            'allow_custom_amount' => $this->allow_custom_amount,
            'max_discount_bdt' => (float) $this->max_discount_bdt,
            'max_discount_percent_of_due' => (float) $this->max_discount_percent_of_due,
            'presets' => $presets,
        ]);

        Notification::make()
            ->title('Collection discount settings saved')
            ->success()
            ->send();
    }
}
