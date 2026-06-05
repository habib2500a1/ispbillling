<?php

namespace Tests\Unit;

use App\Filament\Resources\OltResource\Pages\CreateOlt;
use App\Models\Device;
use App\Support\OltManagementHelper;
use Tests\TestCase;

class NormalizesOltFormDataTest extends TestCase
{
    public function test_hidden_meta_keys_not_exposed_in_meta_extra(): void
    {
        $page = new CreateOlt;
        $method = new \ReflectionMethod($page, 'expandOltFormDataForFill');
        $method->setAccessible(true);

        $expanded = $method->invoke($page, [
            'meta' => [
                'olt_web_url' => '103.29.127.94:8506',
                'aveis_snmp_column_map' => ['mac' => 7, 'status' => 3],
                'custom_note' => 'ok',
            ],
        ]);

        $this->assertArrayNotHasKey('aveis_snmp_column_map', $expanded['meta_extra']);
        $this->assertSame('ok', $expanded['meta_extra']['custom_note'] ?? null);
    }

    public function test_save_preserves_aveis_column_map_and_rejects_object_object_string(): void
    {
        $page = new CreateOlt;
        $method = new \ReflectionMethod($page, 'normalizeOltFormData');
        $method->setAccessible(true);

        $existing = new Device([
            'type' => 'olt',
            'meta' => [
                'aveis_snmp_column_map' => ['mac' => 7, 'label' => 2],
            ],
        ]);

        $normalized = $method->invoke($page, [
            'management_ip' => '103.29.127.94',
            'olt_driver' => 'aveis_epon',
            'meta' => [],
            'meta_extra' => [
                'aveis_snmp_column_map' => '[object Object]',
                'custom_note' => 'keep',
            ],
        ], $existing);

        $this->assertSame(['mac' => 7, 'label' => 2], $normalized['meta']['aveis_snmp_column_map']);
        $this->assertSame('keep', $normalized['meta']['custom_note']);
    }

    public function test_meta_keys_hidden_list_includes_aveis_map(): void
    {
        $this->assertContains(
            OltManagementHelper::META_AVEIS_SNMP_COLUMN_MAP,
            OltManagementHelper::metaKeysHiddenFromExtraForm(),
        );
    }

    public function test_aveis_edit_fill_clears_meta_extra(): void
    {
        $page = new CreateOlt;
        $method = new \ReflectionMethod($page, 'expandOltFormDataForFill');
        $method->setAccessible(true);

        $expanded = $method->invoke($page, [
            'olt_driver' => 'aveis_epon',
            'meta' => [
                'custom_note' => 'ok',
                'aveis_snmp_column_map' => ['mac' => 7],
            ],
        ]);

        $this->assertSame([], $expanded['meta_extra']);
        $this->assertArrayNotHasKey('aveis_snmp_column_map', $expanded['meta']);
    }
}
