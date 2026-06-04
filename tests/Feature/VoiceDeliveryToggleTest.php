<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Models\VoiceSmsCampaign;
use App\Models\VoiceTemplate;
use App\Services\CallCenter\VoiceSmsCampaignRunner;
use App\Services\Sms\SmsTemplateService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceDeliveryToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_template_voice_toggle(): void
    {
        config(['call_center.voice_call.enabled' => true]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'voice-toggle-test'],
            ['name' => 'Voice ISP', 'is_active' => true],
        );
        TenantResolver::fake($tenant->id);

        $voice = VoiceTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Due voice',
            'transcript' => 'Please pay your bill.',
            'is_active' => true,
        ]);

        SmsTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'key' => 'invoice_due_soon',
            'name' => 'Due soon',
            'event_key' => 'invoice_due_soon',
            'body' => 'SMS body',
            'is_enabled' => false,
            'voice_enabled' => true,
            'voice_template_id' => $voice->id,
            'is_active' => true,
        ]);

        $service = app(SmsTemplateService::class);
        $this->assertFalse($service->isEnabled('invoice_due_soon', $tenant->id));
        $this->assertTrue($service->isVoiceFallbackEnabled('invoice_due_soon', $tenant->id));
        $this->assertSame($voice->id, $service->voiceTemplateFor('invoice_due_soon', $tenant->id)?->id);

        SmsTemplate::query()->whereKey($service->find('invoice_due_soon', $tenant->id)?->id)->update([
            'is_enabled' => true,
            'voice_enabled' => true,
        ]);
        $this->assertTrue($service->isEnabled('invoice_due_soon', $tenant->id));
        // SMS on → voice template still linked but reminders use SMS only (no voice path when SMS on)
    }

    public function test_campaign_voice_only_dry_run(): void
    {
        config(['call_center.voice_call.enabled' => true]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'voice-campaign-test'],
            ['name' => 'Camp ISP', 'is_active' => true],
        );

        $template = VoiceTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Blast',
            'transcript' => 'Hello',
            'is_active' => true,
        ]);

        $campaign = VoiceSmsCampaign::query()->create([
            'tenant_id' => $tenant->id,
            'voice_template_id' => $template->id,
            'name' => 'Voice only',
            'send_sms' => false,
            'send_voice' => true,
            'status' => 'draft',
            'target_filters' => ['preset' => 'all_active'],
        ]);

        $stats = app(VoiceSmsCampaignRunner::class)->run($campaign, true);
        $this->assertSame(0, $stats['sms_sent']);
        $this->assertGreaterThanOrEqual(0, $stats['targets']);
    }
}
