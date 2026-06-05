<?php

namespace Tests\Unit;

use App\Filament\Resources\OltResource\Pages\EditOlt;
use App\Support\LivewireSnapshotHealer;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Tests\TestCase;

class LivewireSnapshotHealerTest extends TestCase
{
    public function test_heals_stale_release_token_and_checksum(): void
    {
        $snapshot = [
            'data' => ['foo' => 'bar'],
            'memo' => [
                'id' => 'test-id',
                'name' => 'app.filament.resources.olt-resource.pages.edit-olt',
                'release' => 'a-STALE-TOKEN-',
            ],
            'checksum' => 'invalid',
        ];

        $snapshot['checksum'] = Checksum::generate($snapshot);

        $components = [[
            'snapshot' => json_encode($snapshot),
            'updates' => [],
            'calls' => [],
        ]];

        $result = LivewireSnapshotHealer::healComponents($components);

        $this->assertNotEmpty($result['healed_release']);

        $healed = json_decode((string) $result['components'][0]['snapshot'], true);

        $this->assertSame(
            ReleaseToken::generate(EditOlt::class),
            $healed['memo']['release'],
        );

        $checksum = $healed['checksum'];
        unset($healed['checksum']);
        $this->assertSame(Checksum::generate($healed), $checksum);
    }
}
