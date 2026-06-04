<?php

namespace App\Services\CallCenter;

use App\Models\VoiceSmsCampaign;
use App\Models\VoiceTemplate;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use Illuminate\Support\Facades\Log;

final class VoiceSmsCampaignRunner
{
    public function __construct(
        private readonly VoiceSmsTargetResolver $targets,
        private readonly NotificationDispatcher $dispatcher,
        private readonly VoiceCallGateway $voiceCalls,
    ) {}

    /**
     * @return array{sent: int, failed: int, targets: int, sms_sent: int, voice_sent: int}
     */
    public function run(VoiceSmsCampaign $campaign, bool $dryRun = false): array
    {
        $campaign = $campaign->fresh();

        if (! $campaign->send_sms && ! $campaign->send_voice) {
            throw new \InvalidArgumentException('Enable at least one delivery: SMS or voice call.');
        }

        $template = $campaign->voice_template_id
            ? VoiceTemplate::query()->withoutGlobalScopes()->find($campaign->voice_template_id)
            : null;

        $transcript = trim((string) ($template?->transcript ?? ''));
        if ($transcript === '') {
            throw new \InvalidArgumentException('Voice template must have a transcript.');
        }

        $customers = $this->targets->customers($campaign);
        $targets = $customers->count();

        if ($dryRun) {
            return [
                'sent' => 0,
                'failed' => 0,
                'targets' => $targets,
                'sms_sent' => 0,
                'voice_sent' => 0,
            ];
        }

        $campaign->forceFill([
            'status' => 'running',
            'targets_count' => $targets,
            'sent_count' => 0,
            'failed_count' => 0,
            'voice_sent_count' => 0,
            'voice_failed_count' => 0,
        ])->saveQuietly();

        $smsSent = 0;
        $voiceSent = 0;
        $failed = 0;
        $prefix = (string) config('call_center.voice_sms_message_prefix', '[Voice] ');

        foreach ($customers as $customer) {
            $phone = trim((string) $customer->phone);
            if ($phone === '') {
                $failed++;

                continue;
            }

            $customerFailed = false;

            if ($campaign->send_sms) {
                try {
                    $this->dispatcher->send(
                        (int) $campaign->tenant_id,
                        (int) $customer->id,
                        'voice_sms_campaign',
                        NotificationChannel::SMS,
                        $phone,
                        $prefix.$transcript,
                        [
                            'bypass_event_gate' => true,
                            'voice_template_id' => $template?->id,
                            'campaign_id' => $campaign->id,
                        ],
                    );
                    $smsSent++;
                } catch (\Throwable $e) {
                    $customerFailed = true;
                    Log::warning('voice_sms_campaign.sms_failed', [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($campaign->send_voice) {
                $ok = $this->voiceCalls->placeCall(
                    (int) $campaign->tenant_id,
                    $phone,
                    $transcript,
                    $template?->audio_url,
                    [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'voice_template_id' => $template?->id,
                    ],
                );
                if ($ok) {
                    $voiceSent++;
                } else {
                    $customerFailed = true;
                }
            }

            if ($customerFailed) {
                $failed++;
            }
        }

        $campaign->forceFill([
            'status' => 'completed',
            'sent_count' => $smsSent + $voiceSent,
            'failed_count' => $failed,
            'voice_sent_count' => $voiceSent,
            'voice_failed_count' => $campaign->send_voice ? max(0, $failed) : 0,
            'targets_count' => $targets,
        ])->saveQuietly();

        return [
            'sent' => $smsSent + $voiceSent,
            'failed' => $failed,
            'targets' => $targets,
            'sms_sent' => $smsSent,
            'voice_sent' => $voiceSent,
        ];
    }
}
