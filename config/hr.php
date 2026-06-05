<?php

return [
    'policy' => [
        'office_start_time' => '09:00',
        'late_grace_minutes' => 10,
        'office_public_ip' => '',
        'min_work_hours_before_checkout' => 3,
        'allowed_late_days' => 3,
        'late_fine_amount' => 50,
        'late_salary_cut_trigger_days' => 6,
        'absent_day_deduction_percent' => 100,
        'pf_percent' => 5,
        'biometric_api_secret' => '',
        'public_holidays' => [],
    ],
    'leave_types' => [
        'annual' => 'Annual Leave',
        'sick' => 'Sick Leave',
        'casual' => 'Casual Leave',
        'unpaid' => 'Unpaid Leave',
        'other' => 'Other',
    ],
];
