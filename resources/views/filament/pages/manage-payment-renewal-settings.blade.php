<x-filament-panels::page>
    <style>
        .glass-container {
            position: relative;
            max-w: 56rem;
            margin: 0 auto;
            z-index: 1;
        }
        .glass-glow-1 {
            position: absolute;
            top: -6rem;
            left: -6rem;
            width: 24rem;
            height: 24rem;
            background: rgba(99, 102, 241, 0.2);
            border-radius: 50%;
            filter: blur(64px);
            pointer-events: none;
            z-index: -1;
        }
        .glass-glow-2 {
            position: absolute;
            bottom: -6rem;
            right: -6rem;
            width: 24rem;
            height: 24rem;
            background: rgba(168, 85, 247, 0.2);
            border-radius: 50%;
            filter: blur(64px);
            pointer-events: none;
            z-index: -1;
        }
        .glass-card {
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border-radius: 1.5rem;
            overflow: hidden;
            position: relative;
        }
        .glass-header {
            padding: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            position: relative;
        }
        .glass-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(99, 102, 241, 0.1), transparent);
            opacity: 0.5;
        }
        .glass-icon-wrapper {
            padding: 1rem;
            background: rgba(99, 102, 241, 0.1);
            backdrop-filter: blur(8px);
            border-radius: 1rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);
            flex-shrink: 0;
        }
        .glass-content {
            padding: 2rem;
            background: rgba(0, 0, 0, 0.2);
        }
        .glass-input-group {
            margin-bottom: 1.5rem;
        }
        .glass-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #d1d5db;
            margin-bottom: 0.75rem;
            transition: color 0.3s;
        }
        .glass-input {
            width: 100%;
            background: rgba(17, 24, 39, 0.5);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(75, 85, 99, 0.5);
            border-radius: 1rem;
            color: white;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s;
            appearance: none;
        }
        .glass-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
            background: rgba(17, 24, 39, 0.8);
        }
        .glass-info-box {
            margin-top: 2rem;
            background: rgba(49, 46, 129, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 1rem;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }
        .glass-info-glow {
            position: absolute;
            top: 0;
            right: 0;
            width: 8rem;
            height: 8rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50%;
            filter: blur(24px);
            transform: translate(25%, -25%);
        }
        .glass-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: flex-end;
        }
        .glass-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 2rem;
            background: linear-gradient(to right, #4f46e5, #9333ea);
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
            transition: all 0.3s;
        }
        .glass-btn:hover {
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.5);
            transform: translateY(-1px);
        }
        .glass-btn:active {
            transform: scale(0.98);
        }
    </style>

    <div class="glass-container">
        <div class="glass-glow-1"></div>
        <div class="glass-glow-2"></div>

        <form wire:submit="save">
            <div class="glass-card">
                
                <div class="glass-header">
                    <div class="glass-icon-wrapper">
                        <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="w-8 h-8 text-indigo-400" style="color: #818cf8; width: 2rem; height: 2rem;" />
                    </div>
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: white; margin: 0;">Payment Renewal Configuration</h2>
                        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #9ca3af; line-height: 1.5; max-width: 42rem;">
                            Control how subscriber expiration dates are extended when full payments are collected. This defines the global system logic for the billing cycle.
                        </p>
                    </div>
                </div>

                <div class="glass-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                        
                        <div class="glass-input-group">
                            <label class="glass-label">Default Renewal Rule</label>
                            <div style="position: relative;">
                                <select wire:model="payment_renewal_base" class="glass-input">
                                    <option value="{{ \App\Support\PaymentRenewalPolicy::SMART }}" style="background: #111827;">Smart Strategy (Grace Period + Payment Date)</option>
                                    <option value="{{ \App\Support\PaymentRenewalPolicy::FROM_PAYMENT_DATE }}" style="background: #111827;">Always from Payment Date (Today)</option>
                                    <option value="{{ \App\Support\PaymentRenewalPolicy::FROM_PREVIOUS_EXPIRY }}" style="background: #111827;">Always from Previous Expire Date</option>
                                </select>
                                <div style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                    <x-filament::icon icon="heroicon-m-chevron-down" style="width: 1.25rem; height: 1.25rem; color: #6b7280;" />
                                </div>
                            </div>
                            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: #9ca3af; line-height: 1.5;">
                                <strong style="color: #d1d5db;">Smart:</strong> If paid within grace period, renews from previous expire date. If paid late, renews from today.
                            </p>
                        </div>

                        <div class="glass-input-group">
                            <label class="glass-label">Late Payment Grace (Days)</label>
                            <div style="position: relative;">
                                <input type="number" min="0" max="90" wire:model="payment_renewal_late_grace_days" class="glass-input" placeholder="e.g. 5" style="padding-right: 4rem;" />
                                <div style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                    <span style="font-size: 0.875rem; color: #6b7280; background: rgba(31, 41, 55, 0.8); padding: 0.25rem 0.5rem; border-radius: 0.5rem;">days</span>
                                </div>
                            </div>
                            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: #9ca3af; line-height: 1.5;">
                                Used only in <strong style="color: #d1d5db;">Smart</strong> mode. Number of days after expiry where the customer retains their original billing cycle.
                            </p>
                        </div>
                    </div>

                    <div class="glass-info-box">
                        <div class="glass-info-glow"></div>
                        <div style="background: rgba(99, 102, 241, 0.2); padding: 0.5rem; border-radius: 0.75rem; flex-shrink: 0; z-index: 1;">
                            <x-filament::icon icon="heroicon-o-light-bulb" style="width: 1.25rem; height: 1.25rem; color: #818cf8;" />
                        </div>
                        <div style="z-index: 1;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #a5b4fc; margin: 0;">How Smart Mode Works (Example with 5 Days Grace)</h4>
                            <ul style="margin-top: 0.75rem; font-size: 0.875rem; color: #c7d2fe; list-style-type: disc; padding-left: 1.25rem; line-height: 1.6; opacity: 0.8;">
                                <li>If expiry is <strong style="color: #e0e7ff;">24 May</strong> and paid on <strong style="color: #e0e7ff;">27 May</strong> (within grace): Renews from 24 May.</li>
                                <li>If expiry is <strong style="color: #e0e7ff;">24 May</strong> and paid on <strong style="color: #e0e7ff;">30 May</strong> (outside grace): Renews from 30 May.</li>
                            </ul>
                            <p style="margin-top: 1rem; font-size: 0.75rem; font-weight: 500; color: #818cf8; background: rgba(99, 102, 241, 0.1); display: inline-block; padding: 0.375rem 0.75rem; border-radius: 0.5rem;">
                                Note: You can always override this per-payment at the Bill Collection Desk.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="glass-footer">
                    <button type="submit" class="glass-btn">
                        <x-filament::icon icon="heroicon-m-check-circle" style="width: 1.25rem; height: 1.25rem;" />
                        Save Configuration
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
