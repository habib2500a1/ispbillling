<?php

return [

    'enabled' => (bool) env('ROCKET_ENABLED', false),

    /** Personal / merchant Rocket wallet number (01XXXXXXXXX). */
    'merchant_number' => env('ROCKET_MERCHANT_NUMBER'),

    'merchant_name' => env('ROCKET_MERCHANT_NAME', env('ISP_COMPANY_NAME', 'ISP')),

    /**
     * Optional extra line shown on the payment screen.
     */
    'instructions' => env('ROCKET_PAYMENT_INSTRUCTIONS'),

    /**
     * Minimum length for customer-entered Rocket transaction ID.
     */
    'trx_id_min_length' => (int) env('ROCKET_TRX_ID_MIN_LENGTH', 8),

    /**
     * When true, valid TrxID + amount match (and optional remote verify) posts payment immediately.
     * Otherwise payments queue in admin → Pending gateway payments.
     */
    'auto_verify' => (bool) env('ROCKET_AUTO_VERIFY', false),

    /** Max BDT difference allowed between session amount and submitted amount. */
    'amount_match_tolerance' => (float) env('ROCKET_AMOUNT_TOLERANCE', 0.01),

    /** Optional POST URL — body: transaction_id, amount, secret; JSON must include verified:true */
    'verify_url' => env('ROCKET_VERIFY_URL'),

    'verify_secret' => env('ROCKET_VERIFY_SECRET'),

];
