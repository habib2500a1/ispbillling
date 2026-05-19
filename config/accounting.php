<?php

return [

    'default_vat_rate' => (float) env('ACCOUNTING_DEFAULT_VAT_RATE', 15),

    'auto_post_customer_payments' => (bool) env('ACCOUNTING_AUTO_POST_PAYMENTS', true),

    'auto_post_invoices' => (bool) env('ACCOUNTING_AUTO_POST_INVOICES', false),

    'cash_account_code' => env('ACCOUNTING_CASH_CODE', '1000'),

    'bank_account_code' => env('ACCOUNTING_BANK_CODE', '1100'),

    'revenue_account_code' => env('ACCOUNTING_REVENUE_CODE', '4000'),

    'vat_payable_code' => env('ACCOUNTING_VAT_PAYABLE_CODE', '2100'),

    'ar_account_code' => env('ACCOUNTING_AR_CODE', '1200'),

    'payroll_expense_code' => env('ACCOUNTING_PAYROLL_CODE', '5100'),

    'vendor_expense_code' => env('ACCOUNTING_VENDOR_EXPENSE_CODE', '5200'),

];
