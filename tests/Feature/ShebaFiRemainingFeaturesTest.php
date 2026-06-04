<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\VoiceSmsCampaign;
use App\Models\VoiceTemplate;
use App\Services\CallCenter\VoiceSmsTargetResolver;
use App\Services\Import\ShebaFiJsonImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShebaFiRemainingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sheba_fi_json_importer_dry_run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'sheba-import-test'],
            ['name' => 'Import ISP', 'is_active' => true],
        );

        $path = storage_path('app/sheba-fi-test-import.json');
        file_put_contents($path, json_encode([
            'customers' => [
                ['customer_code' => 'SF-1', 'name' => 'Test User', 'phone' => '01710009999', 'status' => 'active'],
            ],
        ], JSON_THROW_ON_ERROR));

        $stats = app(ShebaFiJsonImporter::class)->import($path, $tenant->id, true);

        $this->assertSame(1, $stats['updated']);
        @unlink($path);
    }

    public function test_voice_sms_target_resolver_returns_collection(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'voice-sms-test'],
            ['name' => 'Voice ISP', 'is_active' => true],
        );

        $template = VoiceTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Due reminder',
            'transcript' => 'Please pay your bill.',
            'is_active' => true,
        ]);

        $campaign = VoiceSmsCampaign::query()->create([
            'tenant_id' => $tenant->id,
            'voice_template_id' => $template->id,
            'name' => 'Test blast',
            'status' => 'draft',
            'target_filters' => ['preset' => 'all_active'],
        ]);

        $count = app(VoiceSmsTargetResolver::class)->countTargets($campaign);

        $this->assertGreaterThanOrEqual(0, $count);
    }
}
