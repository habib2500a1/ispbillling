<?php

namespace App\Services\Import\ISPTrack;

final class ISPTrackImportOrchestrator
{
    public function __construct(
        private readonly ISPTrackPrepService $prep,
        private readonly ISPTrackMasterImporter $master,
        private readonly ISPTrackCustomerImporter $customers,
        private readonly ISPTrackBillingImporter $billing,
        private readonly ISPTrackNetworkSyncService $network,
        private readonly ISPTrackImportVerifier $verifier,
    ) {}

    /**
     * @param  list<int>  $phases
     * @return array<string, mixed>
     */
    public function run(
        string $path,
        int $tenantId,
        array $phases,
        bool $dryRun = false,
        bool $force = false,
        bool $skipNetwork = false,
    ): array {
        $ctx = new ISPTrackImportContext($tenantId, $dryRun, $force);
        $report = ['phases' => []];

        if (in_array(0, $phases, true)) {
            $report['phases'][0] = $this->prep->run($ctx, $path);
        }

        if (in_array(1, $phases, true)) {
            $this->master->run($ctx, $path);
            $report['phases'][1] = $ctx->stats();
        }

        if (in_array(2, $phases, true)) {
            $this->customers->run($ctx, $path);
            $report['phases'][2] = $ctx->stats();
        }

        if (in_array(3, $phases, true)) {
            $this->billing->run($ctx, $path);
            $report['phases'][3] = $ctx->stats();
        }

        if (in_array(4, $phases, true)) {
            $report['phases'][4] = $this->network->run($ctx, $skipNetwork);
        }

        if (in_array(5, $phases, true)) {
            $report['phases'][5] = $this->verifier->run($ctx, $path);
        }

        $report['stats'] = $ctx->stats();

        return $report;
    }
}
