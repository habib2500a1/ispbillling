@php
    $wallet = $this->getWallet();
    $ledger = app(\App\Services\Collector\CollectorLedgerQueryService::class);
    $collectorId = auth()->id();
    $dueLimit = (float) config('collector.due_alert_threshold', 10000);
    $adminDash = $this->canApprove() ? $this->getAdminDashboard() : [];
    $alerts = $this->getFraudAlerts();
@endphp

<x-filament-panels::page>
    <div class="isp-collector-hub space-y-6">
        <header class="isp-collector-hub__hero">
            <h2 class="isp-collector-hub__title">Collection settlement & collector due</h2>
            <p class="isp-collector-hub__sub">
                Customer payment → collector wallet → expense deduction → settlement to admin → cash ledger.
                <strong>Cash in hand = Collected − Deposited − Approved expenses</strong>
            </p>
        </header>

        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm dark:border-sky-800 dark:bg-sky-950/40">
            <p class="font-semibold text-sky-900 dark:text-sky-100">Staff থেকে আংশিক টাকা নেওয়া (যেমন ৫০০ এর মধ্যে ২০০)</p>
            <p class="mt-1 text-sky-800 dark:text-sky-200">
                @if ($this->canApprove())
                    নিচে <strong>Admin control</strong> → <strong>Staff থেকে টাকা গ্রহণ</strong> — staff সিলেক্ট, amount ২০০, Notes লিখুন; বাকি ৩০০ তার due।
                @else
                    <strong>Settlement</strong> ট্যাবে amount ২০০ দিন (পুরো due না), Notes-এ লিখুন; admin approve করলে বাকি due থাকবে।
                @endif
            </p>
        </div>

        @if ($alerts !== [])
            <ul class="isp-collector-alerts">
                @foreach ($alerts as $alert)
                    <li class="isp-collector-alerts__item isp-collector-alerts__item--{{ $alert['severity'] }}">
                        {{ $alert['message'] }}
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($this->canApprove())
            <nav class="isp-collector-nav">
                <button type="button" wire:click="$set('activeView', 'mine')" @class(['isp-collector-nav__btn', 'isp-collector-nav__btn--active' => $activeView === 'mine'])>My wallet</button>
                <button type="button" wire:click="$set('activeView', 'admin')" @class(['isp-collector-nav__btn', 'isp-collector-nav__btn--active' => $activeView === 'admin'])>Admin control</button>
            </nav>
        @endif

        @if ($activeView === 'mine' || ! $this->canApprove())
            <nav class="isp-collector-tabs">
                @foreach (['wallet' => 'Wallet', 'settle' => 'Settlement', 'expense' => 'Expenses', 'closing' => 'Daily closing', 'ledger' => 'Ledger'] as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')" @class(['isp-collector-tabs__btn', 'isp-collector-tabs__btn--active' => $activeTab === $key])>{{ $label }}</button>
                @endforeach
            </nav>

            @if ($activeTab === 'wallet')
                <div class="isp-collector-kpi-grid">
                    <div class="isp-collector-kpi isp-collector-kpi--emerald">
                        <span class="isp-collector-kpi__label">Today collected</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['today_collected'], 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi">
                        <span class="isp-collector-kpi__label">Today deposited</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['today_deposited'] ?? 0, 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi">
                        <span class="isp-collector-kpi__label">Today expenses</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['today_expenses'] ?? 0, 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi">
                        <span class="isp-collector-kpi__label">Total collected</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['total_collected'], 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi">
                        <span class="isp-collector-kpi__label">Deposited</span>
                        <span class="isp-collector-kpi__value isp-collector-kpi__value--sky">{{ number_format($wallet['total_settled'], 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi">
                        <span class="isp-collector-kpi__label">Approved expenses</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['approved_expenses'] ?? 0, 0) }}</span>
                    </div>
                    <div class="isp-collector-kpi isp-collector-kpi--due {{ $wallet['cash_in_hand'] > $dueLimit ? 'isp-collector-kpi--danger' : '' }}">
                        <span class="isp-collector-kpi__label">Cash in hand / due</span>
                        <span class="isp-collector-kpi__value">{{ number_format($wallet['cash_in_hand'], 2) }} BDT</span>
                        @if (($wallet['pending_settlement'] ?? 0) > 0)
                            <span class="isp-collector-kpi__meta">Pending settlement: {{ number_format($wallet['pending_settlement'], 2) }}</span>
                        @endif
                        @if (($wallet['pending_expenses'] ?? 0) > 0)
                            <span class="isp-collector-kpi__meta">Pending expenses: {{ number_format($wallet['pending_expenses'], 2) }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if ($activeTab === 'settle' && $this->canSettle())
                @if ($wallet['cash_in_hand'] > 0)
                    <form wire:submit="submitSettlement" class="isp-collector-panel">
                        <h3 class="isp-collector-panel__title">Admin-কে টাকা জমা (আংশিক বা পুরো)</h3>
                        <p class="isp-collector-panel__hint">
                            হাতে আছে: {{ number_format($wallet['cash_in_hand'], 2) }} BDT — কম amount দিলে বাকি আপনার due থাকবে (যেমন ৫০০ এর মধ্যে ২০০ জমা → ৩০০ due)।
                        </p>
                        <div class="isp-collector-form-grid">
                            <label class="isp-collector-field">
                                <span>Amount (BDT)</span>
                                <input type="number" step="0.01" min="0.01" wire:model="settlementAmount" required />
                            </label>
                            <label class="isp-collector-field">
                                <span>Method</span>
                                <select wire:model="settlementMethod">
                                    @foreach ($this->getMethodOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="isp-collector-field">
                                <span>Reference</span>
                                <input type="text" wire:model="settlementReference" placeholder="Slip #" />
                            </label>
                            <label class="isp-collector-field">
                                <span>Notes</span>
                                <input type="text" wire:model="settlementNotes" placeholder="যেমন: ৫০০ এর মধ্যে ২০০ দিলাম, বাকি ৩০০" />
                            </label>
                        </div>
                        <button type="submit" class="isp-collector-btn isp-collector-btn--primary">Submit settlement</button>
                    </form>
                @else
                    <p class="isp-collector-empty">No cash due to deposit.</p>
                @endif

                <div class="isp-collector-split">
                    <div>
                        <h3 class="isp-collector-section-title">Open collections</h3>
                        @include('filament.pages.partials.collector-open-collections', ['rows' => $ledger->openCollectionsForCollector($collectorId)])
                    </div>
                    <div>
                        <h3 class="isp-collector-section-title">Settlement history</h3>
                        @include('filament.pages.partials.collector-settlements', ['rows' => $ledger->settlementsForCollector($collectorId)])
                    </div>
                </div>
            @endif

            @if ($activeTab === 'expense' && $this->canSettle())
                <form wire:submit="submitExpense" class="isp-collector-panel">
                    <h3 class="isp-collector-panel__title">Add field expense</h3>
                    <p class="isp-collector-panel__hint">Approved expenses reduce your cash-in-hand due. Receipt recommended.</p>
                    <div class="isp-collector-form-grid">
                        <label class="isp-collector-field">
                            <span>Category</span>
                            <select wire:model="expenseCategoryId" required>
                                <option value="">Select…</option>
                                @foreach ($ledger->expenseCategories() as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="isp-collector-field">
                            <span>Amount (BDT)</span>
                            <input type="number" step="0.01" min="0.01" wire:model="expenseAmount" required />
                        </label>
                        <label class="isp-collector-field">
                            <span>Date</span>
                            <input type="date" wire:model="expenseDate" required />
                        </label>
                        <label class="isp-collector-field isp-collector-field--full">
                            <span>Description</span>
                            <input type="text" wire:model="expenseDescription" placeholder="Fuel, transport…" />
                        </label>
                        <label class="isp-collector-field isp-collector-field--full">
                            <span>Receipt / proof</span>
                            <input type="file" wire:model="expenseProof" accept="image/*,application/pdf" />
                        </label>
                    </div>
                    <button type="submit" class="isp-collector-btn isp-collector-btn--primary">Submit expense</button>
                </form>

                <h3 class="isp-collector-section-title">My expenses</h3>
                @include('filament.pages.partials.collector-expenses', ['rows' => $ledger->expensesForCollector($collectorId)])
            @endif

            @if ($activeTab === 'closing' && $this->canSettle())
                <form wire:submit="submitDailyClosing" class="isp-collector-panel">
                    <h3 class="isp-collector-panel__title">Daily closing</h3>
                    <p class="isp-collector-panel__hint">Declare physical cash in hand at end of day. System compares with computed due.</p>
                    <div class="isp-collector-form-grid">
                        <label class="isp-collector-field">
                            <span>Closing date</span>
                            <input type="date" wire:model="closingDate" required />
                        </label>
                        <label class="isp-collector-field">
                            <span>Cash in hand (BDT)</span>
                            <input type="number" step="0.01" min="0" wire:model="closingDeclaredCash" required />
                        </label>
                        <label class="isp-collector-field isp-collector-field--full">
                            <span>Notes</span>
                            <input type="text" wire:model="closingNotes" />
                        </label>
                    </div>
                    <button type="submit" class="isp-collector-btn isp-collector-btn--primary">Submit daily closing</button>
                </form>

                <h3 class="isp-collector-section-title">Closing history</h3>
                @include('filament.pages.partials.collector-closings', ['rows' => $ledger->dailyClosingsForCollector($collectorId)])
            @endif

            @if ($activeTab === 'ledger')
                <h3 class="isp-collector-section-title">Collection ledger timeline</h3>
                <div class="isp-collector-timeline">
                    @forelse ($this->getLedgerTimeline() as $event)
                        <div class="isp-collector-timeline__row isp-collector-timeline__row--{{ $event['type'] }}">
                            <span class="isp-collector-timeline__date">{{ $event['at']?->format('d M Y H:i') }}</span>
                            <span class="isp-collector-timeline__label">{{ $event['label'] }}</span>
                            <span class="isp-collector-timeline__amount {{ $event['amount'] < 0 ? 'isp-collector-timeline__amount--out' : '' }}">
                                {{ $event['amount'] < 0 ? '' : '+' }}{{ number_format($event['amount'], 2) }}
                            </span>
                        </div>
                    @empty
                        <p class="isp-collector-empty">No ledger events yet.</p>
                    @endforelse
                </div>
            @endif
        @endif

        @if ($activeView === 'admin' && $this->canApprove())
            <div class="isp-collector-kpi-grid isp-collector-kpi-grid--4">
                <div class="isp-collector-kpi isp-collector-kpi--danger">
                    <span class="isp-collector-kpi__label">Total collector due</span>
                    <span class="isp-collector-kpi__value">{{ number_format($adminDash['total_due'] ?? 0, 0) }} BDT</span>
                </div>
                <div class="isp-collector-kpi">
                    <span class="isp-collector-kpi__label">Pending settlements</span>
                    <span class="isp-collector-kpi__value">{{ $adminDash['pending_settlements'] ?? 0 }}</span>
                </div>
                <div class="isp-collector-kpi">
                    <span class="isp-collector-kpi__label">Pending expenses</span>
                    <span class="isp-collector-kpi__value">{{ $adminDash['pending_expenses'] ?? 0 }}</span>
                </div>
                <div class="isp-collector-kpi">
                    <span class="isp-collector-kpi__label">Active collectors</span>
                    <span class="isp-collector-kpi__value">{{ count($adminDash['leaderboard'] ?? []) }}</span>
                </div>
            </div>

            <form wire:submit="receiveCashFromStaff" class="isp-collector-panel isp-collector-panel--highlight">
                <h3 class="isp-collector-panel__title">Staff থেকে টাকা গ্রহণ (partial / full)</h3>
                <p class="isp-collector-panel__hint">
                    Staff-এর হাতে যত cash due আছে তার চেয়ে বেশি নেওয়া যাবে না। কম নিলে বাকি staff due-তে থাকবে।
                </p>
                @if (count($this->getStaffReceiveOptions()) === 0)
                    <p class="isp-collector-empty">কোনো staff-এর কাছে এখন cash due নেই।</p>
                @else
                    <div class="isp-collector-form-grid">
                        <label class="isp-collector-field isp-collector-field--full">
                            <span>Staff / collector</span>
                            <select wire:model.live="adminReceiveStaffId" required>
                                <option value="">Select staff…</option>
                                @foreach ($this->getStaffReceiveOptions() as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if ($this->selectedStaffCashDue() !== null)
                            <p class="isp-collector-field isp-collector-field--full text-sm text-amber-800 dark:text-amber-200">
                                Cash in hand: <strong>{{ number_format($this->selectedStaffCashDue(), 2) }} BDT</strong>
                                @if (is_numeric($adminReceiveAmount) && (float) $adminReceiveAmount > 0 && $this->staffReceiveRemainingDue() !== null && $this->staffReceiveRemainingDue() < $this->selectedStaffCashDue())
                                    · এই টাকা নিলে বাকি due: <strong>{{ number_format($this->staffReceiveRemainingDue(), 2) }} BDT</strong>
                                @endif
                            </p>
                        @endif
                        <label class="isp-collector-field">
                            <span>Amount received (BDT)</span>
                            <input type="number" step="0.01" min="0.01" @if($this->selectedStaffCashDue()) max="{{ $this->selectedStaffCashDue() }}" @endif wire:model.live="adminReceiveAmount" required />
                        </label>
                        <label class="isp-collector-field">
                            <span>Method</span>
                            <select wire:model="adminReceiveMethod">
                                @foreach ($this->getMethodOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="isp-collector-field">
                            <span>Reference</span>
                            <input type="text" wire:model="adminReceiveReference" placeholder="Slip #" />
                        </label>
                        <label class="isp-collector-field">
                            <span>Notes</span>
                            <input type="text" wire:model="adminReceiveNotes" placeholder="৫০০ এর মধ্যে ২০০ নিলাম, বাকি ৩০০" />
                        </label>
                    </div>
                    <button type="submit" class="isp-collector-btn isp-collector-btn--primary">Record cash received</button>
                @endif
            </form>

            <div class="isp-collector-split">
                <div>
                    <h3 class="isp-collector-section-title">Pending settlements</h3>
                    @if ($rejectingSettlementId)
                        <form wire:submit="confirmReject" class="isp-collector-reject-box">
                            <p>Reject settlement</p>
                            <textarea wire:model="rejectionReason" rows="2" required></textarea>
                            <div class="isp-collector-reject-box__actions">
                                <button type="submit" class="isp-collector-btn isp-collector-btn--danger">Confirm</button>
                                <button type="button" wire:click="cancelReject" class="isp-collector-btn">Cancel</button>
                            </div>
                        </form>
                    @endif
                    @forelse ($ledger->pendingSettlements() as $pending)
                        <div class="isp-collector-approval-card">
                            <div>
                                <strong>{{ $pending->collector?->name }}</strong>
                                <span class="isp-collector-mono">{{ $pending->settlement_number }}</span>
                                <span class="isp-collector-muted">{{ $pending->submitted_at?->format('d M Y H:i') }}</span>
                            </div>
                            <div class="isp-collector-approval-card__side">
                                <strong>{{ number_format($pending->amount, 2) }} BDT</strong>
                                <div class="isp-collector-approval-card__btns">
                                    <button type="button" wire:click="approveSettlement({{ $pending->id }})" class="isp-collector-btn isp-collector-btn--ok">Approve</button>
                                    <button type="button" wire:click="startReject({{ $pending->id }})" class="isp-collector-btn isp-collector-btn--danger-outline">Reject</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="isp-collector-empty">No pending settlements.</p>
                    @endforelse
                </div>
                <div>
                    <h3 class="isp-collector-section-title">Pending expenses</h3>
                    @if ($rejectingExpenseId)
                        <form wire:submit="confirmRejectExpense" class="isp-collector-reject-box">
                            <p>Reject expense</p>
                            <textarea wire:model="expenseRejectionReason" rows="2" required></textarea>
                            <div class="isp-collector-reject-box__actions">
                                <button type="submit" class="isp-collector-btn isp-collector-btn--danger">Confirm</button>
                                <button type="button" wire:click="cancelRejectExpense" class="isp-collector-btn">Cancel</button>
                            </div>
                        </form>
                    @endif
                    @forelse ($ledger->pendingExpenses() as $exp)
                        <div class="isp-collector-approval-card">
                            <div>
                                <strong>{{ $exp->collector?->name }}</strong> · {{ $exp->category?->name }}
                                <span class="isp-collector-mono">{{ $exp->expense_number }}</span>
                            </div>
                            <div class="isp-collector-approval-card__side">
                                <strong>{{ number_format($exp->amount, 2) }} BDT</strong>
                                <div class="isp-collector-approval-card__btns">
                                    <button type="button" wire:click="approveExpense({{ $exp->id }})" class="isp-collector-btn isp-collector-btn--ok">Approve</button>
                                    <button type="button" wire:click="startRejectExpense({{ $exp->id }})" class="isp-collector-btn isp-collector-btn--danger-outline">Reject</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="isp-collector-empty">No pending expenses.</p>
                    @endforelse
                </div>
            </div>

            <h3 class="isp-collector-section-title">Staff-wise due</h3>
            @include('filament.pages.partials.collector-leaderboard', ['rows' => $adminDash['leaderboard'] ?? []])

            @if (! empty($adminDash['expense_breakdown']))
                <h3 class="isp-collector-section-title">Expense breakdown (this month)</h3>
                <ul class="isp-collector-expense-breakdown">
                    @foreach ($adminDash['expense_breakdown'] as $row)
                        <li><span>{{ $row['category'] }}</span><strong>{{ number_format($row['total'], 0) }} BDT</strong></li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</x-filament-panels::page>
