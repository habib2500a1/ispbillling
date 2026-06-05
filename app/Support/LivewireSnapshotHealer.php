<?php

namespace App\Support;

use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\ComponentRegistry;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Throwable;

class LivewireSnapshotHealer
{
    public const EDIT_OLT_COMPONENT = 'app.filament.resources.olt-resource.pages.edit-olt';

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{components: array<int, array<string, mixed>>, healed_release: list<string>, healed_snapshot: list<string>}
     */
    public static function healComponents(array $components): array
    {
        $healedRelease = [];
        $healedSnapshot = [];

        foreach ($components as $index => $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                continue;
            }

            $snapshot = json_decode($component['snapshot'], true);

            if (! is_array($snapshot) || ! is_string($snapshot['memo']['name'] ?? null)) {
                continue;
            }

            $componentName = $snapshot['memo']['name'];
            $changed = false;

            try {
                $componentClass = app(ComponentRegistry::class)->getClass($componentName);
                $expectedRelease = ReleaseToken::generate($componentClass);
            } catch (Throwable) {
                $expectedRelease = null;
            }

            if ($expectedRelease !== null && ($snapshot['memo']['release'] ?? '') !== $expectedRelease) {
                $snapshot['memo']['release'] = $expectedRelease;
                $changed = true;
                $healedRelease[] = $componentName;
            }

            if ($componentName === self::EDIT_OLT_COMPONENT) {
                $sanitized = self::sanitizeAveisOltSnapshot($snapshot);
                if ($sanitized !== $snapshot) {
                    $snapshot = $sanitized;
                    $changed = true;
                    $healedSnapshot[] = $componentName;
                }
            }

            if ($changed) {
                unset($snapshot['checksum']);
                $snapshot['checksum'] = Checksum::generate($snapshot);
                $components[$index]['snapshot'] = json_encode($snapshot);
            }
        }

        return [
            'components' => $components,
            'healed_release' => $healedRelease,
            'healed_snapshot' => $healedSnapshot,
        ];
    }

    /**
     * Aveis OLTs store nested SNMP maps in meta; if they leak into Livewire state the
     * checksum fails and Save returns 419. Strip them before Livewire verifies the snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function sanitizeAveisOltSnapshot(array $snapshot): array
    {
        $data = $snapshot['data'] ?? null;

        if (! is_array($data)) {
            return $snapshot;
        }

        $isAveis = OltManagementHelper::isAveisDriver($data['olt_driver'] ?? null);

        foreach (['meta', 'meta_extra'] as $bag) {
            if (! is_array($data[$bag] ?? null)) {
                continue;
            }

            unset($data[$bag][OltManagementHelper::META_AVEIS_SNMP_COLUMN_MAP]);

            if ($isAveis) {
                foreach ($data[$bag] as $key => $value) {
                    if (! is_scalar($value) && $value !== null) {
                        unset($data[$bag][$key]);
                    }
                }
            }
        }

        if ($isAveis) {
            $data['meta_extra'] = [];
        }

        $snapshot['data'] = $data;

        return $snapshot;
    }
}
