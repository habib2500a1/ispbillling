<?php

namespace App\Filament\Pages;

use App\Models\CollectorCollection;
use App\Models\CollectorExpense;
use App\Models\CollectorSettlement;
use App\Models\User;
use App\Support\TenantResolver;
use App\Services\Collector\CollectorLedgerQueryService;
use App\Services\Collector\CollectorSettlementService;
use App\Services\Collector\CollectorStaffResolver;
use App\Services\Collector\CollectorWalletService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CollectorCashHub extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static string $view = 'filament.pages.collector-cash-hub';

    protected static ?string $navigationLabel = 'Collector settlement';

    protected static ?string $title = 'Collection settlement & collector due';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'collector-settlement';

    public string $activeView = 'mine';

    public string $activeTab = 'wallet';

    public string $settlementAmount = '';

    public string $settlementMethod = 'cash';

    public string $settlementReference = '';

    public string $settlementNotes = '';

    public ?int $rejectingSettlementId = null;

    public string $rejectionReason = '';

    public string $expenseAmount = '';

    public ?int $expenseCategoryId = null;

    public string $expenseDescription = '';

    public string $expenseDate = '';

    /** @var TemporaryUploadedFile|null */
    public $expenseProof = null;

    public ?int $rejectingExpenseId = null;

    public string $expenseRejectionReason = '';

    public string $closingDeclaredCash = '';

    public string $closingDate = '';

    public string $closingNotes = '';

    public ?int $adminReceiveStaffId = null;

    public string $adminReceiveAmount = '';

    public string $adminReceiveMethod = 'cash';

    public string $adminReceiveReference = '';

    public string $adminReceiveNotes = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->can('collections.view')
            || $user->can('collections.settle')
            || $user->can('collections.approve')
            || $user->can('payments.reconcile');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && (bool) config('collector.enabled', true);
    }

    public function mount(): void
    {
        $this->expenseDate = now()->toDateString();
        $this->closingDate = now()->toDateString();
        $wallet = $this->getWallet();
        $this->closingDeclaredCash = (string) ($wallet['cash_in_hand'] ?? 0);

        if ($this->canApprove()) {
            $this->activeView = 'admin';
        }
    }

    public function canApprove(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole(['super-admin', 'isp-admin', 'admin'])
                || $user->can('collections.approve')
                || $user->can('payments.reconcile'));
    }

    public function canSettle(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('collections.settle')
                || $user->can('payments.add')
                || $user->hasRole(['cashier', 'branch-manager', 'isp-admin', 'admin']));
    }

    /**
     * @return array<string, mixed>
     */
    public function getWallet(): array
    {
        return app(CollectorWalletService::class)->wallet((int) auth()->id());
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminDashboard(): array
    {
        return app(CollectorWalletService::class)->adminDashboard();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFraudAlerts(): array
    {
        return app(CollectorWalletService::class)->fraudAlerts((int) auth()->id());
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getLedgerTimeline(): \Illuminate\Support\Collection
    {
        return app(CollectorWalletService::class)->ledgerTimeline((int) auth()->id());
    }

    public function submitSettlement(): void
    {
        abort_unless($this->canSettle(), 403);

        $this->validate([
            'settlementAmount' => 'required|numeric|min:0.01',
            'settlementMethod' => 'required|string',
        ]);

        app(CollectorSettlementService::class)->submitSettlement(
            collectorId: (int) auth()->id(),
            amount: (float) $this->settlementAmount,
            paymentMethod: $this->settlementMethod,
            reference: $this->settlementReference ?: null,
            notes: $this->settlementNotes ?: null,
        );

        $this->settlementAmount = '';
        $this->settlementReference = '';
        $this->settlementNotes = '';

        Notification::make()->title('Settlement submitted')->success()->send();
    }

    public function receiveCashFromStaff(): void
    {
        abort_unless($this->canApprove(), 403);

        $this->validate([
            'adminReceiveStaffId' => 'required|integer|exists:users,id',
            'adminReceiveAmount' => 'required|numeric|min:0.01',
            'adminReceiveMethod' => 'required|string',
        ]);

        $collectorId = (int) $this->adminReceiveStaffId;
        $amount = round((float) $this->adminReceiveAmount, 2);
        $wallet = app(CollectorWalletService::class)->wallet($collectorId);

        if ($amount > $wallet['cash_in_hand'] + 0.009) {
            throw ValidationException::withMessages([
                'adminReceiveAmount' => 'Amount exceeds staff cash in hand ('.number_format($wallet['cash_in_hand'], 2).' BDT).',
            ]);
        }

        $settlement = app(CollectorSettlementService::class)->submitSettlement(
            collectorId: $collectorId,
            amount: $amount,
            paymentMethod: $this->adminReceiveMethod,
            reference: $this->adminReceiveReference ?: null,
            notes: $this->adminReceiveNotes ?: null,
            submittedBy: (int) auth()->id(),
        );

        $settlement = app(CollectorSettlementService::class)->approveSettlement($settlement);

        $remaining = app(CollectorWalletService::class)->wallet($collectorId)['cash_in_hand'];

        $this->adminReceiveAmount = '';
        $this->adminReceiveReference = '';
        $this->adminReceiveNotes = '';

        $body = number_format($amount, 2).' BDT received from staff.';
        if ($remaining > 0) {
            $body .= ' Remaining due: '.number_format($remaining, 2).' BDT.';
        }

        Notification::make()
            ->title('Cash received from staff')
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    public function getStaffReceiveOptions(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $walletService = app(CollectorWalletService::class);

        $collectorIds = CollectorCollection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('collector_id');

        $options = [];
        foreach ($collectorIds as $collectorId) {
            $due = $walletService->wallet((int) $collectorId)['cash_in_hand'];
            if ($due <= 0) {
                continue;
            }
            $user = User::query()->find($collectorId);
            if ($user === null) {
                continue;
            }
            $options[(int) $collectorId] = $user->name.' · due '.number_format($due, 0).' BDT';
        }

        foreach (app(CollectorStaffResolver::class)->collectableStaffOptions() as $id => $label) {
            if (isset($options[$id])) {
                continue;
            }
            $due = $walletService->wallet((int) $id)['cash_in_hand'];
            if ($due > 0) {
                $options[$id] = $label.' · due '.number_format($due, 0).' BDT';
            }
        }

        asort($options);

        return $options;
    }

    public function selectedStaffCashDue(): ?float
    {
        if ($this->adminReceiveStaffId === null || $this->adminReceiveStaffId < 1) {
            return null;
        }

        return app(CollectorWalletService::class)->wallet($this->adminReceiveStaffId)['cash_in_hand'];
    }

    public function staffReceiveRemainingDue(): ?float
    {
        $due = $this->selectedStaffCashDue();
        if ($due === null) {
            return null;
        }

        $amount = (float) $this->adminReceiveAmount;
        if ($amount <= 0) {
            return $due;
        }

        return max(0.0, round($due - $amount, 2));
    }

    public function submitExpense(): void
    {
        abort_unless($this->canSettle(), 403);

        $this->validate([
            'expenseAmount' => 'required|numeric|min:0.01',
            'expenseCategoryId' => 'required|integer',
            'expenseDate' => 'required|date',
            'expenseProof' => 'nullable|file|max:4096',
        ]);

        $proofPath = null;
        if ($this->expenseProof) {
            $proofPath = $this->expenseProof->store('collector-expenses/'.auth()->id(), 'local');
        }

        app(CollectorWalletService::class)->submitExpense(
            collectorId: (int) auth()->id(),
            amount: (float) $this->expenseAmount,
            categoryId: (int) $this->expenseCategoryId,
            description: $this->expenseDescription ?: null,
            expenseDate: $this->expenseDate,
            proofPath: $proofPath,
        );

        $this->expenseAmount = '';
        $this->expenseDescription = '';
        $this->expenseProof = null;

        Notification::make()->title('Expense submitted')->success()->send();
    }

    public function submitDailyClosing(): void
    {
        abort_unless($this->canSettle(), 403);

        $this->validate([
            'closingDate' => 'required|date',
            'closingDeclaredCash' => 'required|numeric|min:0',
        ]);

        $closing = app(CollectorWalletService::class)->submitDailyClosing(
            collectorId: (int) auth()->id(),
            closingDate: $this->closingDate,
            declaredCashInHand: (float) $this->closingDeclaredCash,
            notes: $this->closingNotes ?: null,
        );

        $body = $closing->status === 'flagged'
            ? 'Cash variance detected — admin will review.'
            : 'Daily closing recorded.';

        Notification::make()->title('Daily closing submitted')->body($body)->success()->send();
    }

    public function approveSettlement(int $settlementId): void
    {
        abort_unless($this->canApprove(), 403);
        $settlement = CollectorSettlement::query()->findOrFail($settlementId);
        app(CollectorSettlementService::class)->approveSettlement($settlement);
        Notification::make()->title('Settlement approved')->success()->send();
    }

    public function approveExpense(int $expenseId): void
    {
        abort_unless($this->canApprove(), 403);
        $expense = CollectorExpense::query()->findOrFail($expenseId);
        app(CollectorWalletService::class)->approveExpense($expense);
        Notification::make()->title('Expense approved')->success()->send();
    }

    public function startReject(int $settlementId): void
    {
        $this->rejectingSettlementId = $settlementId;
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingSettlementId = null;
        $this->rejectionReason = '';
    }

    public function confirmReject(): void
    {
        abort_unless($this->canApprove() && $this->rejectingSettlementId, 403);
        $this->validate(['rejectionReason' => 'required|string|min:3|max:500']);
        $settlement = CollectorSettlement::query()->findOrFail($this->rejectingSettlementId);
        app(CollectorSettlementService::class)->rejectSettlement($settlement, $this->rejectionReason);
        $this->cancelReject();
        Notification::make()->title('Settlement rejected')->warning()->send();
    }

    public function startRejectExpense(int $expenseId): void
    {
        $this->rejectingExpenseId = $expenseId;
        $this->expenseRejectionReason = '';
    }

    public function cancelRejectExpense(): void
    {
        $this->rejectingExpenseId = null;
        $this->expenseRejectionReason = '';
    }

    public function confirmRejectExpense(): void
    {
        abort_unless($this->canApprove() && $this->rejectingExpenseId, 403);
        $this->validate(['expenseRejectionReason' => 'required|string|min:3|max:500']);
        $expense = CollectorExpense::query()->findOrFail($this->rejectingExpenseId);
        app(CollectorWalletService::class)->rejectExpense($expense, $this->expenseRejectionReason);
        $this->cancelRejectExpense();
        Notification::make()->title('Expense rejected')->warning()->send();
    }

    /**
     * @return array<string, string>
     */
    public function getMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'bank' => 'Bank transfer',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'other' => 'Other',
        ];
    }
}
