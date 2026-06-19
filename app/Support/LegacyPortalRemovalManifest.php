<?php

namespace App\Support;

/**
 * Inventory of pay.anetbd.com / legacy portal integration files.
 * Used by isp:remove-legacy-portal when you cut over to native billing only.
 */
final class LegacyPortalRemovalManifest
{
    /**
     * Standalone files safe to delete — sync, import, mirror, probes (no historical data helpers).
     *
     * @return list<string> paths relative to project root
     */
    public static function deletableFiles(): array
    {
        return [
            // Config (replaced with cutover stub by the command)
            'config/legacy_portal.php',
            'config/isp_digital.php',

            // Console commands
            'app/Console/Commands/SyncLegacyPortalDailyCommand.php',
            'app/Console/Commands/SyncLegacyPortalCollectionsCommand.php',
            'app/Console/Commands/SyncLegacyPortalLineGraceCommand.php',
            'app/Console/Commands/SyncLegacyPortalPackageProfilesCommand.php',
            'app/Console/Commands/SyncLegacyPortalSubscriberLifecycleCommand.php',
            'app/Console/Commands/SyncFromLegacyPortalCommand.php',
            'app/Console/Commands/SyncLegacyPortalCurrentBillingCommand.php',
            'app/Console/Commands/SyncLegacyPortalDetailsCommand.php',
            'app/Console/Commands/SyncLegacyPortalCustomerPackagesCommand.php',
            'app/Console/Commands/SyncLegacyPortalPricesCommand.php',
            'app/Console/Commands/RestoreLegacyPortalNetworkCommand.php',
            'app/Console/Commands/ReconcileLegacyPortalSuspendedCommand.php',
            'app/Console/Commands/ImportLegacyPortalResellersCommand.php',
            'app/Console/Commands/ImportLegacyPortalFullCommand.php',
            'app/Console/Commands/ImportLegacyPortalEmployeesCommand.php',
            'app/Console/Commands/ImportLegacyPortalExtrasCommand.php',
            'app/Console/Commands/ImportLegacyPortalClientsCommand.php',
            'app/Console/Commands/ImportLegacyPortalBillingCommand.php',
            'app/Console/Commands/LegacyPortalOnuSyncCommand.php',
            'app/Console/Commands/AuditLegacyPortalImportCommand.php',
            'app/Console/Commands/AlignLegacyPortalWithRemoteCommand.php',
            'app/Console/Commands/MirrorLegacyPortalCommand.php',
            'app/Console/Commands/VerifyLegacyPortalFullSyncCommand.php',
            'app/Console/Commands/BackfillLegacyPaymentCollectorsCommand.php',

            // Import / sync services
            'app/Services/Import/LegacyPortalSessionClient.php',
            'app/Services/Import/LegacyPortalCustomerImporter.php',
            'app/Services/Import/LegacyPortalBillingImporter.php',
            'app/Services/Import/LegacyPortalMacResellerImporter.php',
            'app/Services/Import/LegacyPortalCollectionReconcileService.php',
            'app/Services/Import/LegacyPortalResellerPackageSyncService.php',
            'app/Services/Import/LegacyPortalMacResellerCustomerMatcher.php',
            'app/Services/Import/LegacyPortalPriceSyncService.php',
            'app/Services/Import/LegacyPortalCurrentBillingSyncService.php',
            'app/Services/Import/LegacyPortalCustomerPackageSyncService.php',
            'app/Services/Import/LegacyPortalRawMirrorService.php',
            'app/Services/Import/LegacyPortalSubscriberLifecycleSyncService.php',
            'app/Services/Import/LegacyPortalSmsImporter.php',
            'app/Services/Import/LegacyPortalOverdueEvaluator.php',
            'app/Services/Import/LegacyPortalSubscriberStatusReconciler.php',
            'app/Services/Import/LegacyPortalDashboardSummaryProvider.php',
            'app/Services/Import/LegacyPortalCustomerDetailsSyncService.php',
            'app/Services/Import/LegacyPortalCollectorSyncService.php',
            'app/Services/Import/LegacyPortalInvoiceAligner.php',
            'app/Services/Import/LegacyPortalBillingReconciler.php',
            'app/Services/Import/LegacyPortalEmployeeImporter.php',
            'app/Services/Import/LegacyPortalApplicationUserImporter.php',
            'app/Services/Import/CustomerDueSnapshotApplicator.php',

            // Optical bridge (legacy portal ONU pipeline)
            'app/Services/Optical/LegacyPortalOnuAutoLinkService.php',
            'app/Services/Optical/LegacyPortalOnuPipelineService.php',

            // Support (live sync only)
            'app/Support/LegacyPortalPassword.php',
            'app/Support/LegacyPortalDateParser.php',

            // Models + admin UI
            'app/Models/LegacyPortalMirrorRecord.php',
            'app/Models/LegacyPortalMirrorRun.php',
            'app/Filament/Pages/LegacyPortalSyncStatusPage.php',
            'resources/views/filament/pages/legacy-portal-sync-status.blade.php',

            // Scripts & probes
            'scripts/sync-all-legacy-data.sh',
            'scripts/probe_legacy_portal.php',
            'scripts/probe_legacy_portal_billing.php',
            'scripts/probe_legacy_portal_prepaid.php',
            'scripts/probe_legacy_portal_reseller.php',
            'scripts/probe_legacy_portal_extras.php',
            'scripts/probe_legacy_portal_users.php',
            'scripts/probe_legacy_portal_staff.php',
            'scripts/probe_legacy_portal_sms_collector.php',

            // Tests (legacy sync)
            'tests/Unit/LegacyPortalPackageSpeedTest.php',
            'tests/Feature/LegacyPortalOnuAutoLinkTest.php',
        ];
    }

    /**
     * Files kept for reading old import_source / meta on customers & payments.
     * Delete only with --include-data-helpers (advanced cutover).
     *
     * @return list<string>
     */
    public static function historicalDataHelpers(): array
    {
        return [
            'app/Support/LegacyPortalSource.php',
            'app/Support/LegacyPortalBillNotes.php',
            'app/Support/LegacyPortalPackageSpeed.php',
            'app/Support/BillingPortalLabel.php',
            'app/Support/PaymentCollectionSource.php',
        ];
    }

    /**
     * Mixed files that reference legacy portal — review after removal.
     *
     * @return list<string>
     */
    public static function mixedIntegrationFiles(): array
    {
        return [
            'bootstrap/app.php',
            'app/Services/Mobile/StaffBillingKpiResolver.php',
            'app/Services/Dashboard/BillingDashboardMetricsService.php',
            'app/Filament/Widgets/TodaySnapshotWidget.php',
            'app/Filament/Pages/StaffPerformanceReport.php',
            'app/Services/Billing/CollectionDeskReportService.php',
            'app/Services/Billing/PackagePriceResolver.php',
            'app/Services/Billing/BillCollectionSearchService.php',
            'app/Services/Billing/InvoiceGenerator.php',
            'app/Services/Network/NetworkAccessCoordinator.php',
            'app/Support/CustomerBalanceDue.php',
            'app/Support/CustomerAccountScopes.php',
            'database/seeders/AutomaticProcessSeeder.php',
            '.env.example',
        ];
    }

    /**
     * @return list<string> .env keys to remove or disable
     */
    public static function envKeys(): array
    {
        return [
            'LEGACY_PORTAL_URL',
            'LEGACY_PORTAL_USERNAME',
            'LEGACY_PORTAL_PASSWORD',
            'LEGACY_PORTAL_SYNC_PASSWORD',
            'LEGACY_PORTAL_DAILY_SYNC_ENABLED',
            'LEGACY_PORTAL_DAILY_SYNC_AT',
            'LEGACY_PORTAL_COLLECTIONS_SYNC_EVERY_MINUTES',
            'LEGACY_PORTAL_SYNC_VOID_ORPHANS',
            'LEGACY_PORTAL_RAW_MIRROR_ENABLED',
            'LEGACY_PORTAL_RAW_MIRROR_AT',
            'LEGACY_PORTAL_COLLECTION_REPORT_SOURCE',
            'LEGACY_PORTAL_BILL_HISTORY_LIMIT',
            'LEGACY_PORTAL_PAYMENT_HISTORY_LIMIT',
            'LEGACY_PORTAL_DAILY_SYNC_IMPORT_BATCH',
            'LEGACY_PORTAL_DAILY_SYNC_FORCE_DETAILS',
            'LEGACY_PORTAL_DAILY_SYNC_FORCE_EXTRAS',
            'LEGACY_PORTAL_DAILY_SYNC_ONU_ENABLED',
            'LEGACY_PORTAL_DAILY_SYNC_VERIFY_SAMPLE',
            'LEGACY_PORTAL_MAC_RESELLER_COMMISSION_PERCENT',
            'LEGACY_PORTAL_MAC_RESELLER_PORTAL_PASSWORD',
            'ISP_DIGITAL_URL',
            'ISP_DIGITAL_USERNAME',
            'ISP_DIGITAL_PASSWORD',
            'OPTICAL_LEGACY_PORTAL_AUTO_SYNC',
        ];
    }
}
