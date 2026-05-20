<?php

namespace App\Services\Portal;

use App\Models\BandwidthSample;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\SubscriberLiveTrafficService;
use App\Support\BandwidthDirection;
use Carbon\Carbon;

final class CustomerBandwidthService
{
    /**
     * @return array{
     *   online: bool,
     *   download_bps: ?int,
     *   upload_bps: ?int,
     *   total_download: int,
     *   total_upload: int,
     *   session_started: ?string,
     *   framed_ip: ?string,
     *   chart: array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>},
     *   today_download: int,
     *   today_upload: int
     * }
     */
    public function liveStats(Customer $customer): array
    {
        $session = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->first();

        $today = BandwidthUsageDaily::query()
            ->where('customer_id', $customer->id)
            ->whereDate('usage_date', today())
            ->first();

        $online = $session !== null;
        $downloadBps = $session?->liveDownloadBps();
        $uploadBps = $session?->liveUploadBps();
        $chart = ['labels' => [], 'download_mbps' => [], 'upload_mbps' => [], 'granularity' => 'per_second'];

        if ($online && config('bandwidth.subscriber_live_mikrotik_enabled', true)) {
            $live = app(SubscriberLiveTrafficService::class);
            $tick = $live->tick($customer);
            $live->maybePersistSessionRates($customer, $tick['rx_bps'], $tick['tx_bps']);
            $chart = $tick['chart'];
            $chart['granularity'] = 'per_second';
            if ($tick['rx_bps'] !== null) {
                $downloadBps = $tick['rx_bps'];
            }
            if ($tick['tx_bps'] !== null) {
                $uploadBps = $tick['tx_bps'];
            }
        } elseif ($online) {
            $chart = $this->liveChartPerSecond($customer, true);
        } else {
            $chart = $this->chartPerSecondFromSamples($customer);
            $chart['granularity'] = 'per_second';
        }

        return [
            'online' => $online,
            'download_bps' => $downloadBps,
            'upload_bps' => $uploadBps,
            'total_download' => (int) ($session?->bytes_in ?? 0),
            'total_upload' => (int) ($session?->bytes_out ?? 0),
            'session_started' => $session?->started_at?->toIso8601String(),
            'framed_ip' => $session?->framed_ip,
            'chart' => $chart,
            'today_download' => (int) ($today?->bytes_in ?? 0),
            'today_upload' => (int) ($today?->bytes_out ?? 0),
        ];
    }

    /**
     * Rolling Mbps-per-second chart for live views (mobile / portal).
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>, granularity: string}
     */
    public function liveChartPerSecond(Customer $customer, bool $online): array
    {
        if ($online) {
            $tick = app(SubscriberLiveTrafficService::class)->tick($customer);
            $chart = $tick['chart'];
            $chart['granularity'] = 'per_second';

            return $chart;
        }

        $chart = $this->chartPerSecondFromSamples($customer);
        $chart['granularity'] = 'per_second';

        return $chart;
    }

    /**
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public function chartPerSecondFromSamples(Customer $customer, int $minutes = 2, int $maxPoints = 120): array
    {
        $since = now()->subMinutes($minutes);
        $samples = BandwidthSample::query()
            ->where('customer_id', $customer->id)
            ->where('sampled_at', '>=', $since)
            ->where(fn ($q) => $q->where('rate_in_bps', '>', 0)->orWhere('rate_out_bps', '>', 0))
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->copy()->startOfSecond()->toDateTimeString();
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => 0, 'up' => 0, 'at' => $s->sampled_at->copy()->startOfSecond()];
            }
            $buckets[$key]['down'] = max($buckets[$key]['down'], (int) $s->rate_in_bps);
            $buckets[$key]['up'] = max($buckets[$key]['up'], (int) $s->rate_out_bps);
        }

        if ($buckets === []) {
            $session = PppSessionLog::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->first();
            $down = (int) ($session?->liveDownloadBps() ?? 0);
            $up = (int) ($session?->liveUploadBps() ?? 0);
            if ($down > 0 || $up > 0) {
                return [
                    'labels' => [now()->format('H:i:s')],
                    'download_mbps' => [round($down / 1_000_000, 4)],
                    'upload_mbps' => [round($up / 1_000_000, 4)],
                ];
            }

            return ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []];
        }

        ksort($buckets);
        if (count($buckets) > $maxPoints) {
            $buckets = array_slice($buckets, -$maxPoints, null, true);
        }

        $labels = [];
        $download = [];
        $upload = [];
        foreach ($buckets as $data) {
            $labels[] = ($data['at'] ?? now())->format('H:i:s');
            $download[] = round($data['down'] / 1_000_000, 4);
            $upload[] = round($data['up'] / 1_000_000, 4);
        }

        return ['labels' => $labels, 'download_mbps' => $download, 'upload_mbps' => $upload];
    }

    /**
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public function chartForCustomer(Customer $customer, int $hours = 12): array
    {
        $since = now()->subHours($hours);

        if (BandwidthSample::query()->getConnection()->getDriverName() === 'pgsql') {
            $rows = BandwidthSample::query()
                ->where('customer_id', $customer->id)
                ->where('sampled_at', '>=', $since)
                ->selectRaw("date_trunc('hour', sampled_at) as bucket")
                ->selectRaw('AVG(rate_in_bps) as avg_in')
                ->selectRaw('AVG(rate_out_bps) as avg_out')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->limit(max(1, $hours) + 1)
                ->get();

            $labels = [];
            $download = [];
            $upload = [];

            foreach ($rows as $row) {
                $labels[] = Carbon::parse($row->bucket)->format('H:i');
                $download[] = $row->avg_in ? round((float) $row->avg_in / 1_000_000, 2) : 0;
                $upload[] = $row->avg_out ? round((float) $row->avg_out / 1_000_000, 2) : 0;
            }
        } else {
            return $this->chartForCustomerInMemory($customer, $hours, $since);
        }

        if ($labels === []) {
            $labels = [now()->format('H:i')];
            $download = [0];
            $upload = [0];
        }

        return [
            'labels' => $labels,
            'download_mbps' => $download,
            'upload_mbps' => $upload,
        ];
    }

    /**
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    private function chartForCustomerInMemory(Customer $customer, int $hours, Carbon $since): array
    {
        $samples = BandwidthSample::query()
            ->where('customer_id', $customer->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $sample) {
            $key = $sample->sampled_at->format('Y-m-d H:00');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => [], 'up' => []];
            }
            if ($sample->rate_in_bps) {
                $buckets[$key]['down'][] = (int) $sample->rate_in_bps;
            }
            if ($sample->rate_out_bps) {
                $buckets[$key]['up'][] = (int) $sample->rate_out_bps;
            }
        }

        $labels = [];
        $download = [];
        $upload = [];
        foreach ($buckets as $hour => $data) {
            $labels[] = Carbon::parse($hour)->format('H:i');
            $download[] = count($data['down']) ? round(array_sum($data['down']) / count($data['down']) / 1_000_000, 2) : 0;
            $upload[] = count($data['up']) ? round(array_sum($data['up']) / count($data['up']) / 1_000_000, 2) : 0;
        }

        if ($labels === []) {
            $labels = [now()->format('H:i')];
            $download = [0];
            $upload = [0];
        }

        return [
            'labels' => $labels,
            'download_mbps' => $download,
            'upload_mbps' => $upload,
        ];
    }

    public function formatLive(?int $bps): string
    {
        return BandwidthDirection::formatBps($bps);
    }

    /**
     * @return array{bytes_in: int, bytes_out: int, peak_in_bps: ?int, peak_out_bps: ?int}
     */
    public function monthlyUsage(Customer $customer): array
    {
        $start = now()->startOfMonth()->toDateString();

        $rows = BandwidthUsageDaily::query()
            ->where('customer_id', $customer->id)
            ->where('usage_date', '>=', $start)
            ->get(['bytes_in', 'bytes_out', 'peak_rate_in_bps', 'peak_rate_out_bps']);

        return [
            'bytes_in' => (int) $rows->sum('bytes_in'),
            'bytes_out' => (int) $rows->sum('bytes_out'),
            'peak_in_bps' => $rows->max('peak_rate_in_bps') ? (int) $rows->max('peak_rate_in_bps') : null,
            'peak_out_bps' => $rows->max('peak_rate_out_bps') ? (int) $rows->max('peak_rate_out_bps') : null,
        ];
    }
}
