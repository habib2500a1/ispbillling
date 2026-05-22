<?php

use App\Support\PaymentGateway;

return [

    /**
     * SMS forwarder app (PipraPay-style) posts parsed MFS SMS here.
     * Header: X-MFS-Device-Key: {MFS_SMS_DEVICE_API_KEY}
     */
    'sms_ingest' => [
        'enabled' => (bool) env('MFS_SMS_INGEST_ENABLED', false),
        'api_key' => env('MFS_SMS_DEVICE_API_KEY'),
        /** OFF = TrxID match works right after SMS ingest (recommended with auto_approve_sms). */
        'require_sms_approved' => (bool) env('MFS_SMS_REQUIRE_APPROVED', false),
        /** ON = ingested SMS immediately usable for TrxID auto-verify. */
        'auto_approve_sms' => (bool) env('MFS_SMS_AUTO_APPROVE', true),
        /** Match customer_code / PPPoE from SMS reference (0790 = 790) and auto-record payment. */
        'auto_approve_by_reference' => (bool) env('MFS_SMS_AUTO_APPROVE_BY_REFERENCE', true),
        /** Match registered panel phone on SMS sender number. */
        'match_sender_phone' => (bool) env('MFS_SMS_MATCH_SENDER_PHONE', true),
        /** One phone → multiple subscriber IDs: split payment across open dues. */
        'split_same_phone_customers' => (bool) env('MFS_SMS_SPLIT_SAME_PHONE', true),
    ],

    'amount_tolerance' => (float) env('MFS_AMOUNT_TOLERANCE', 0.01),

    'gateways' => [
        PaymentGateway::BKASH => [
            'trx_min_length' => (int) env('BKASH_PERSONAL_TRX_MIN', 8),
            'trx_pattern' => env('BKASH_PERSONAL_TRX_PATTERN', '/^[A-Z0-9]{8,20}$/'),
            'auto_verify' => (bool) env('BKASH_PERSONAL_AUTO_VERIFY', true),
        ],
        PaymentGateway::NAGAD => [
            'trx_min_length' => (int) env('NAGAD_PERSONAL_TRX_MIN', 8),
            'trx_pattern' => env('NAGAD_PERSONAL_TRX_PATTERN', '/^[A-Z0-9]{6,20}$/'),
            'auto_verify' => (bool) env('NAGAD_PERSONAL_AUTO_VERIFY', true),
        ],
        PaymentGateway::ROCKET => [
            'trx_min_length' => (int) env('ROCKET_TRX_ID_MIN_LENGTH', 8),
            'trx_pattern' => env('ROCKET_PERSONAL_TRX_PATTERN', '/^[A-Z0-9]{8,20}$/'),
            'auto_verify' => (bool) env('ROCKET_AUTO_VERIFY', false),
        ],
    ],

];
