<?php

namespace App\Services\Hr;

use App\Models\AppSetting;

final class HrPolicySettings
{
    public const SETTINGS_KEY = 'hr.policy';

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $defaults = (array) config('hr.policy', []);
        $stored = AppSetting::getStoredValue(self::SETTINGS_KEY);
        if ($stored !== null && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return self::normalize(array_merge($defaults, $decoded));
            }
        }

        return self::normalize($defaults);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function save(array $payload): void
    {
        AppSetting::putValue(self::SETTINGS_KEY, json_encode(self::normalize($payload), JSON_THROW_ON_ERROR));
    }

    public static function biometricApiUrl(): string
    {
        return url('/api/attendance/biometric');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $holidays = $data['public_holidays'] ?? [];
        if (is_string($holidays)) {
            $holidays = array_filter(array_map('trim', explode("\n", $holidays)));
        }

        return [
            'office_start_time' => (string) ($data['office_start_time'] ?? '09:00'),
            'late_grace_minutes' => max(0, (int) ($data['late_grace_minutes'] ?? 10)),
            'office_public_ip' => (string) ($data['office_public_ip'] ?? ''),
            'min_work_hours_before_checkout' => max(0, (int) ($data['min_work_hours_before_checkout'] ?? 3)),
            'allowed_late_days' => max(0, (int) ($data['allowed_late_days'] ?? 3)),
            'late_fine_amount' => max(0, (float) ($data['late_fine_amount'] ?? 50)),
            'late_salary_cut_trigger_days' => max(1, (int) ($data['late_salary_cut_trigger_days'] ?? 6)),
            'absent_day_deduction_percent' => min(100, max(0, (float) ($data['absent_day_deduction_percent'] ?? 100))),
            'pf_percent' => min(100, max(0, (float) ($data['pf_percent'] ?? 5))),
            'biometric_api_secret' => (string) ($data['biometric_api_secret'] ?? ''),
            'public_holidays' => array_values($holidays),
        ];
    }
}
