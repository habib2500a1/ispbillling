<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerAnnouncement;
use App\Services\Resellers\ResellerQuotaService;
use App\Services\Resellers\ResellerWalletLedgerService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\View\View;

class ResellerHubController extends Controller
{
    public function index(
        ResellerWalletLedgerService $ledger,
        ResellerQuotaService $quota,
    ): View {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $portal = app(ResellerPortalSession::class);

        $announcements = ResellerAnnouncement::query()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('is_active', true)
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->filter(fn (ResellerAnnouncement $a) => $a->isVisibleTo($reseller));

        $tools = array_filter([
            $portal->canPortal(ResellerPortalPermission::WALLET_VIEW)
                ? ['route' => 'reseller.wallet.overview', 'label' => 'Wallet & ledger', 'desc' => 'Main, bonus, credit limit', 'icon' => '💳'] : null,
            $portal->canPortal(ResellerPortalPermission::WALLET_VIEW)
                ? ['route' => 'reseller.due-account', 'label' => 'Due account', 'desc' => 'HQ payable vs customer due', 'icon' => '📒'] : null,
            $portal->canPortal(ResellerPortalPermission::REPORTS_VIEW)
                ? ['route' => 'reseller.reports.enterprise', 'label' => 'Analytics', 'desc' => 'Revenue, P&L, package sales', 'icon' => '📊'] : null,
            $portal->canPortal(ResellerPortalPermission::SUB_RESELLER_VIEW)
                ? ['route' => 'reseller.sub-resellers.index', 'label' => 'Partners', 'desc' => $reseller->children()->count().' sub-partner(s)', 'icon' => '🤝'] : null,
            $portal->canPortal(ResellerPortalPermission::SUB_RESELLER_CREATE)
                ? ['route' => 'reseller.sub-resellers.create', 'label' => 'Add partner', 'desc' => 'Create sub-reseller', 'icon' => '➕'] : null,
            $portal->canPortal(ResellerPortalPermission::CUSTOMER_TRANSFER)
                ? ['route' => 'reseller.customer-transfers.index', 'label' => 'Transfers', 'desc' => 'Move subscribers', 'icon' => '↔️'] : null,
            $portal->canPortal(ResellerPortalPermission::BRANDING_MANAGE)
                ? ['route' => 'reseller.branding.edit', 'label' => 'White-label', 'desc' => 'Logo, colors, domain', 'icon' => '🎨'] : null,
            $portal->canPortal(ResellerPortalPermission::API_KEYS_MANAGE) && $reseller->api_access_enabled
                ? ['route' => 'reseller.api-keys.index', 'label' => 'API keys', 'desc' => 'Integrations & logs', 'icon' => '🔑'] : null,
            $reseller->api_access_enabled
                ? ['route' => 'docs.reseller-api', 'label' => 'API docs', 'desc' => 'OpenAPI & Swagger UI', 'icon' => '📘'] : null,
            $portal->canPortal(ResellerPortalPermission::INTERNAL_TICKET_MANAGE)
                ? ['route' => 'reseller.internal-tickets.index', 'label' => 'HQ support', 'desc' => 'Internal tickets', 'icon' => '🎫'] : null,
            ['route' => 'reseller.security.index', 'label' => 'Security', 'desc' => 'Login history & IP', 'icon' => '🔒'],
            $portal->canPortal(ResellerPortalPermission::ANNOUNCEMENTS_VIEW)
                ? ['route' => 'reseller.announcements.index', 'label' => 'Announcements', 'desc' => 'News from HQ', 'icon' => '📢'] : null,
        ]);

        return view('reseller.hub', [
            'reseller' => $reseller,
            'portal' => $portal,
            'tools' => $tools,
            'announcements' => $announcements,
            'quota' => $quota->usage($reseller),
            'availableMain' => $ledger->availableMainBalance($reseller),
            'isLowBalance' => $ledger->isLowBalance($reseller),
        ]);
    }
}
