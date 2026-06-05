<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksHrAccess;
use App\Filament\Resources\EmployeeResource;
use App\Services\Hr\HrPolicySettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class HrSalaryPoliciesPage extends Page
{
    use ChecksHrAccess;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.hr-salary-policies';

    protected static ?string $navigationLabel = 'Salary Policies';

    protected static ?string $title = 'HR Policy';

    protected static ?string $slug = 'hr-salary-policies';

    protected static bool $shouldRegisterNavigation = false;

    public string $office_start_time = '09:00';

    public int $late_grace_minutes = 10;

    public string $office_public_ip = '';

    public int $min_work_hours_before_checkout = 3;

    public int $allowed_late_days = 3;

    public string $late_fine_amount = '50';

    public int $late_salary_cut_trigger_days = 6;

    public string $absent_day_deduction_percent = '100';

    public string $pf_percent = '5';

    public string $biometric_api_secret = '';

    public string $public_holidays = '';

    public function mount(): void
    {
        $policy = HrPolicySettings::get();
        $this->office_start_time = (string) $policy['office_start_time'];
        $this->late_grace_minutes = (int) $policy['late_grace_minutes'];
        $this->office_public_ip = (string) $policy['office_public_ip'];
        $this->min_work_hours_before_checkout = (int) $policy['min_work_hours_before_checkout'];
        $this->allowed_late_days = (int) $policy['allowed_late_days'];
        $this->late_fine_amount = (string) $policy['late_fine_amount'];
        $this->late_salary_cut_trigger_days = (int) $policy['late_salary_cut_trigger_days'];
        $this->absent_day_deduction_percent = (string) $policy['absent_day_deduction_percent'];
        $this->pf_percent = (string) $policy['pf_percent'];
        $this->biometric_api_secret = (string) $policy['biometric_api_secret'];
        $holidays = $policy['public_holidays'] ?? [];
        $this->public_holidays = is_array($holidays) ? implode("\n", $holidays) : '';
    }

    public function save(): void
    {
        if (! static::canManagePayroll()) {
            abort(403);
        }

        HrPolicySettings::save([
            'office_start_time' => $this->office_start_time,
            'late_grace_minutes' => $this->late_grace_minutes,
            'office_public_ip' => $this->office_public_ip,
            'min_work_hours_before_checkout' => $this->min_work_hours_before_checkout,
            'allowed_late_days' => $this->allowed_late_days,
            'late_fine_amount' => (float) $this->late_fine_amount,
            'late_salary_cut_trigger_days' => $this->late_salary_cut_trigger_days,
            'absent_day_deduction_percent' => (float) $this->absent_day_deduction_percent,
            'pf_percent' => (float) $this->pf_percent,
            'biometric_api_secret' => $this->biometric_api_secret,
            'public_holidays' => $this->public_holidays,
        ]);

        Notification::make()->title('HR policy saved')->success()->send();
    }

    public function generateBiometricKey(): void
    {
        $this->biometric_api_secret = Str::random(48);
        Notification::make()->title('Key generated — click Save to store')->success()->send();
    }

    public static function employeesUrl(): string
    {
        return EmployeeResource::getUrl('index');
    }
}
