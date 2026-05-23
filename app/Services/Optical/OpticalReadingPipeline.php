<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuHealthScore;
use App\Models\OnuSignalLog;
use App\Services\Optical\Analysis\OpticalSignalAnalyzer;
use App\Services\Optical\Normalization\OpticalPowerNormalizer;
use App\Services\Optical\Validation\OpticalSignalValidator;
use App\Services\Mobile\MobileBroadcastService;
use App\Support\OnuSignalLevel;
use Carbon\Carbon;

/**
 * OLT → normalize → validate (spike filter) → analyze → persist.
 */
final class OpticalReadingPipeline
{
    public function __construct(
        private readonly OpticalPowerNormalizer $normalizer,
        private readonly OpticalSignalValidator $validator,
        private readonly OpticalSignalAnalyzer $analyzer,
    ) {}

    /**
     * @param  array{
     *   rx_raw?: mixed,
     *   tx_raw?: mixed,
     *   temperature?: mixed,
     *   voltage?: mixed,
     *   oper_status?: ?string,
     *   vendor_profile?: ?string,
     *   source?: string
     * }  $reading
     * @return array{device: Device, log: OnuSignalLog}
     */
    public function ingest(Device $onu, array $reading, ?Carbon $at = null): array
    {
        $at ??= now();
        $vendor = $reading['vendor_profile']
            ?? $onu->gpon_profile
            ?? $onu->olt?->olt_driver
            ?? 'generic_gpon';

        $snmpRxRaw = $reading['rx_raw'] ?? null;
        $snmpTxRaw = $reading['tx_raw'] ?? null;

        $rawRx = ($reading['already_dbm'] ?? false)
            ? $this->toFloat($snmpRxRaw)
            : $this->normalizer->normalizeRx($snmpRxRaw, (string) $vendor);
        $rawTx = ($reading['already_dbm'] ?? false)
            ? $this->toFloat($snmpTxRaw)
            : $this->normalizer->normalizeTx($snmpTxRaw, (string) $vendor);

        // Fresh BDCOM SNMP sync must not be averaged with pre-fix corrupted history.
        $bypassSmooth = in_array($reading['source'] ?? '', ['bdcom_snmp', 'bdcom_resync'], true)
            || ($reading['bypass_smoothing'] ?? false);

        $smoothed = $bypassSmooth
            ? [
                'rx_dbm' => $rawRx,
                'tx_dbm' => $rawTx,
                'is_spike' => false,
                'sample_count' => 1,
                'rx_stddev' => null,
            ]
            : $this->validator->smooth((int) $onu->id, $rawRx, $rawTx);

        $oper = strtolower((string) ($reading['oper_status'] ?? $onu->onu_oper_status ?? 'unknown'));
        $analysis = $this->analyzer->analyze(
            $onu,
            $smoothed['rx_dbm'],
            $smoothed['tx_dbm'],
            $smoothed['rx_stddev'],
            $oper,
        );

        if ($smoothed['rx_dbm'] !== null) {
            $onu->rx_power_dbm = $smoothed['rx_dbm'];
        }
        if ($smoothed['tx_dbm'] !== null) {
            $onu->tx_power_dbm = $smoothed['tx_dbm'];
        }
        if (isset($reading['oper_status'])) {
            $onu->onu_oper_status = $oper;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $meta['optical'] = array_merge($meta['optical'] ?? [], [
            'snmp_rx_raw' => $snmpRxRaw,
            'snmp_tx_raw' => $snmpTxRaw,
            'raw_rx_dbm' => $rawRx,
            'raw_tx_dbm' => $rawTx,
            'smoothed_at' => $at->toIso8601String(),
            'poll_source' => $reading['source'] ?? 'snmp',
            'vendor_profile' => $vendor,
        ]);
        $onu->meta = $meta;
        $onu->last_polled_at = $at;
        $onu->save();

        $rxLevel = $analysis['rx_level'];
        $txLevel = OnuSignalLevel::classifyTx($smoothed['tx_dbm']);

        $log = OnuSignalLog::query()->create([
            'tenant_id' => $onu->tenant_id,
            'device_id' => $onu->id,
            'olt_id' => $onu->olt_id,
            'olt_port_id' => $onu->olt_port_id,
            'rx_power_dbm' => $smoothed['rx_dbm'],
            'tx_power_dbm' => $smoothed['tx_dbm'],
            'raw_rx_power_dbm' => $rawRx,
            'raw_tx_power_dbm' => $rawTx,
            'temperature_c' => $this->toFloat($reading['temperature'] ?? null),
            'voltage_v' => $this->toFloat($reading['voltage'] ?? null),
            'is_spike' => $smoothed['is_spike'],
            'poll_source' => $reading['source'] ?? 'snmp',
            'rx_level' => $rxLevel,
            'tx_level' => $txLevel,
            'onu_oper_status' => $oper,
            'health_score' => OnuSignalLevel::healthScoreFromRxLevel($rxLevel),
            'granularity' => 'snapshot',
            'sampled_at' => $at,
            'meta' => [
                'sample_count' => $smoothed['sample_count'],
                'rx_stddev' => $smoothed['rx_stddev'],
                'fiber_health_score' => $analysis['fiber_health_score'],
            ],
        ]);

        OnuHealthScore::query()->updateOrCreate(
            ['device_id' => $onu->id],
            [
                'tenant_id' => $onu->tenant_id,
                'health_score' => OnuSignalLevel::healthScoreFromRxLevel($rxLevel),
                'stability_score' => $analysis['stability_score'],
                'rx_level' => $rxLevel,
                'root_cause_hint' => $analysis['root_cause_hint'],
                'rx_trend_dbm' => $analysis['rx_trend_dbm'],
                'smoothed_rx_dbm' => $smoothed['rx_dbm'],
                'smoothed_tx_dbm' => $smoothed['tx_dbm'],
                'rx_stddev_db' => $smoothed['rx_stddev'],
                'fiber_health_score' => $analysis['fiber_health_score'],
                'computed_at' => $at,
                'meta' => [
                    'neighbor_delta_db' => $analysis['neighbor_delta_db'],
                ],
            ],
        );

        $fresh = $onu->fresh();

        if (config('broadcasting.default') !== 'log') {
            app(MobileBroadcastService::class)->onuSignalChanged(
                (int) $fresh->tenant_id,
                (int) $fresh->id,
                [
                    'rx_dbm' => $smoothed['rx_dbm'],
                    'tx_dbm' => $smoothed['tx_dbm'],
                    'rx_level' => $rxLevel,
                    'oper_status' => $oper,
                    'health_score' => OnuSignalLevel::healthScoreFromRxLevel($rxLevel),
                ],
            );
        }

        return ['device' => $fresh, 'log' => $log];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round((float) $value, 3) : null;
    }
}
