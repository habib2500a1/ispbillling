@php
    $stats = $this->getTemplateStats();
@endphp

<x-filament-panels::page class="isp-sms-templates-page">
    <div class="space-y-5">
        <section class="isp-sms-templates-hero">
            <div class="isp-sms-templates-hero__main">
                <p class="isp-sms-templates-hero__eyebrow">SMS Service</p>
                <h2 class="isp-sms-templates-hero__title">SMS Templates</h2>
                <p class="isp-sms-templates-hero__sub">
                    Automated messages for billing, clients, support, and OTP. Toggle off to stop sending without deleting the template.
                </p>
            </div>
            <div class="isp-sms-templates-hero__stats">
                <div class="isp-sms-templates-stat">
                    <span class="isp-sms-templates-stat__label">Templates</span>
                    <strong>{{ number_format($stats['total']) }}</strong>
                </div>
                <div class="isp-sms-templates-stat isp-sms-templates-stat--on">
                    <span class="isp-sms-templates-stat__label">Enabled</span>
                    <strong>{{ number_format($stats['enabled']) }}</strong>
                </div>
                <div class="isp-sms-templates-stat">
                    <span class="isp-sms-templates-stat__label">Disabled</span>
                    <strong>{{ number_format($stats['disabled']) }}</strong>
                </div>
            </div>
        </section>

        <section class="isp-sms-templates-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
