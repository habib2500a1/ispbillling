<?php

namespace App\Support;

/**
 * Default SMS templates ported from anetbd / ispbilling catalog.
 */
final class SmsTemplateCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   name: string,
     *   event_key: ?string,
     *   body: string,
     *   placeholders: list<string>,
     *   sort_order: int,
     *   example_en?: string,
     *   example_bn?: string,
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'create_customer',
                'name' => 'Client Created',
                'event_key' => 'client_created',
                'body' => 'প্রিয় গ্রাহক, আপনার অ্যাকাউন্ট সফলভাবে তৈরি করা হয়েছে। ক্লায়েন্ট কোড: {ClientID} সার্ভার ID/IP: {UserName} পাসওয়ার্ড: {Password} প্যাকেজ: {Package} মাসিক বিল: {MonthlyBillAmount} ধন্যবাদ ও শুভেচ্ছা {CompanyName} বিস্তারিত: {CompanyMobile}',
                'placeholders' => ['ClientID', 'UserName', 'Password', 'Package', 'MonthlyBillAmount', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 12,
            ],
            [
                'key' => 'payment_collection',
                'name' => 'Money Receipt',
                'event_key' => 'payment_success',
                'body' => 'Dear {CustomerName}, Your bill collected {PaidAmount}/= (id: {ClientID}). Your bill due {Due}/=. Thank you — {CompanyName} HelpLine {CompanyMobile}',
                'placeholders' => ['CustomerName', 'PaidAmount', 'ClientID', 'Due', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 16,
            ],
            [
                'key' => 'bill_generate',
                'name' => 'New Invoice / Monthly Bill',
                'event_key' => 'invoice_created',
                'body' => 'প্রিয় গ্রাহক, আপনার {Month} মাসের বিল {Due} টাকা। ইনভয়েস: {invoice_number} শেষ তারিখ: {BillingLastDate}। {CompanyName} {CompanyMobile}',
                'placeholders' => ['Month', 'Due', 'invoice_number', 'BillingLastDate', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 17,
            ],
            [
                'key' => 'reminder',
                'name' => 'Due Template',
                'event_key' => 'invoice_due',
                'body' => 'প্রিয় গ্রাহক, অনুগ্রহ করে ইন্টারনেট বিল পরিশোধ করুন। ক্লায়েন্ট কোড: {ClientID} বকেয়া: {Due} বিলিং শেষ তারিখ: {BillingLastDate} ধন্যবাদ {CompanyName} {CompanyMobile}',
                'placeholders' => ['ClientID', 'Due', 'BillingLastDate', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 18,
            ],
            [
                'key' => 'auto_temporary_disable_alert',
                'name' => 'Client Disable',
                'event_key' => 'client_disable',
                'body' => 'প্রিয় ক্লায়েন্ট, আপনার অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে। ক্লায়েন্ট কোড: {ClientID} মাসিক বিল: {MonthlyBillAmount} বিলিং শেষ তারিখ: {BillingLastDate}',
                'placeholders' => ['ClientID', 'MonthlyBillAmount', 'BillingLastDate'],
                'sort_order' => 19,
            ],
            [
                'key' => 'all_customers',
                'name' => 'All Customers Broadcast',
                'event_key' => null,
                'body' => 'প্রিয় গ্রাহক, {CompanyName} থেকে গুরুত্বপূর্ণ বার্তা: {message} সহায়তা: {CompanyMobile}',
                'placeholders' => ['CompanyName', 'message', 'CompanyMobile'],
                'sort_order' => 20,
            ],
            [
                'key' => 'complain_to_customer',
                'name' => 'Support Token Created',
                'event_key' => 'support_token_created',
                'body' => 'প্রিয় গ্রাহক, আমরা আপনার সমস্যা তালিকাভুক্ত করেছি। ক্লায়েন্ট আইডি: {ClientID} কল: {CompanyMobile} | {CompanyName}',
                'placeholders' => ['ClientID', 'CompanyMobile', 'CompanyName'],
                'sort_order' => 21,
            ],
            [
                'key' => 'complain_list',
                'name' => 'Support Solved',
                'event_key' => 'support_solved',
                'body' => 'প্রিয় গ্রাহক, ক্লায়েন্ট কোড: {ClientID} সমস্যা: {Problem} টিকেট সম্পন্ন হয়েছে। ধন্যবাদ {CompanyName}',
                'placeholders' => ['ClientID', 'Problem', 'CompanyName'],
                'sort_order' => 22,
            ],
            [
                'key' => 'portal_otp',
                'name' => 'Verification Code (OTP)',
                'event_key' => 'portal_otp',
                'body' => '{CompanyName}-তে আপনার OTP: {VerificationCode} — ১০ মিনিটের মধ্যে ব্যবহার করুন।',
                'placeholders' => ['CompanyName', 'VerificationCode', 'code', 'minutes'],
                'sort_order' => 24,
            ],
            [
                'key' => 'outage',
                'name' => 'Outage / Maintenance',
                'event_key' => 'outage',
                'body' => '{CompanyName}: {message}',
                'placeholders' => ['CompanyName', 'message'],
                'sort_order' => 31,
            ],
        ];
    }
}
