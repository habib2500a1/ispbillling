<?php

namespace App\Support;

/**
 * Default SMS templates (Bengali + English mix from legacy ISP billing).
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
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'new_client_request',
                'name' => 'New Client Request',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, {CompanyName}-এ স্বাগতম, আমাদের পরিষেবা নেওয়ার জন্য আপনাকে ধন্যবাদ। আশা করি খুব শীঘ্রই আপনার সংযোগ চালু হবে। ভালোবাসার সাথে শুভেচ্ছা {CompanyName} {CompanyMobile}',
                'placeholders' => ['CustomerName', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 11,
            ],
            [
                'key' => 'client_created',
                'name' => 'Client Created',
                'event_key' => 'client_created',
                'body' => 'প্রিয় গ্রাহক, আপনার অ্যাকাউন্ট সফলভাবে তৈরি করা হয়েছে। ক্লায়েন্ট কোড: {ClientID} সার্ভার ID/IP: {UserName} পাসওয়ার্ড: {Password} প্যাকেজ: {Package} মাসিক বিল: {MonthlyBillAmount} ধন্যবাদ ও শুভেচ্ছা {CompanyName} বিস্তারিত: {CompanyMobile}',
                'placeholders' => ['ClientID', 'UserName', 'Password', 'Package', 'MonthlyBillAmount', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 12,
            ],
            [
                'key' => 'todo_assigned',
                'name' => 'Todo Assigned',
                'event_key' => null,
                'body' => 'প্রিয় {EmpName}, আমরা আপনার জন্য একটি টাস্ক সংযুক্ত করেছি। অনুগ্রহ করে চেক করুন। ধন্যবাদ {CompanyName}',
                'placeholders' => ['EmpName', 'CompanyName'],
                'sort_order' => 13,
            ],
            [
                'key' => 'client_disable',
                'name' => 'Client Disable',
                'event_key' => 'client_disable',
                'body' => 'প্রিয় ক্লায়েন্ট, আপনার অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে। ক্লায়েন্ট কোড: {ClientID} মাসিক বিল: {MonthlyBillAmount} বিলিং শেষ তারিখ: {BillingLastDate}',
                'placeholders' => ['ClientID', 'MonthlyBillAmount', 'BillingLastDate'],
                'sort_order' => 14,
            ],
            [
                'key' => 'client_enable',
                'name' => 'Client Enable',
                'event_key' => 'client_enable',
                'body' => 'প্রিয় ক্লায়েন্ট, আপনার অ্যাকাউন্ট সফলভাবে সক্রিয় করা হয়েছে। ক্লায়েন্ট কোড: {ClientID} ব্যবহারকারী: {UserName} পাসওয়ার্ড: {Password} প্যাকেজ: {Package} মাসিক বিল: {MonthlyBillAmount} বিলিং শেষ: {BillingLastDate} ধন্যবাদ {CompanyName}',
                'placeholders' => ['ClientID', 'UserName', 'Password', 'Package', 'MonthlyBillAmount', 'BillingLastDate', 'CompanyName'],
                'sort_order' => 15,
            ],
            [
                'key' => 'money_receipt',
                'name' => 'Money Receipt',
                'event_key' => 'payment_success',
                'body' => 'Dear {CustomerName}, Your bill collected {PaidAmount}/= (id: {ClientID}). Your bill due {Due}/=. Thank you — {CompanyName} HelpLine {CompanyMobile}',
                'placeholders' => ['CustomerName', 'PaidAmount', 'ClientID', 'Due', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 16,
            ],
            [
                'key' => 'due_template',
                'name' => 'Due Template',
                'event_key' => 'invoice_due',
                'body' => 'প্রিয় গ্রাহক, অনুগ্রহ করে ইন্টারনেট বিল পরিশোধ করুন। ক্লায়েন্ট কোড: {ClientID} বকেয়া: {Due} বিলিং শেষ তারিখ: {BillingLastDate} ধন্যবাদ {CompanyName} {CompanyMobile}',
                'placeholders' => ['ClientID', 'Due', 'BillingLastDate', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 17,
            ],
            [
                'key' => 'line_man_support',
                'name' => 'Line Man Support Notification',
                'event_key' => 'support_staff_alert',
                'body' => 'হ্যালো সাপোর্ট, দ্রুত সমাধান প্রয়োজন। আইডি: {UserName} মোবাইল: {CustomerNumber} সমস্যা: {Problem}',
                'placeholders' => ['UserName', 'CustomerNumber', 'Problem'],
                'sort_order' => 18,
            ],
            [
                'key' => 'support_solved',
                'name' => 'Support Solved',
                'event_key' => 'support_solved',
                'body' => 'প্রিয় গ্রাহক, ক্লায়েন্ট কোড: {ClientID} সমস্যা: {Problem} টিকেট সম্পন্ন হয়েছে। ধন্যবাদ {CompanyName}',
                'placeholders' => ['ClientID', 'Problem', 'CompanyName'],
                'sort_order' => 19,
            ],
            [
                'key' => 'support_token_created',
                'name' => 'Support Token Created',
                'event_key' => 'support_token_created',
                'body' => 'প্রিয় গ্রাহক, আমরা আপনার সমস্যা তালিকাভুক্ত করেছি। ক্লায়েন্ট আইডি: {ClientID} কল: {CompanyMobile} | {CompanyName}',
                'placeholders' => ['ClientID', 'CompanyMobile', 'CompanyName'],
                'sort_order' => 20,
            ],
            [
                'key' => 'mac_reseller_fund_receipt',
                'name' => 'MAC Reseller Fund Receipt',
                'event_key' => null,
                'body' => 'প্রিয় {MACResellerName}, আমরা তহবিলের জন্য আপনার অর্থপ্রদান পেয়েছি। ফান্ডের পরিমাণ: {FundAmount}',
                'placeholders' => ['MACResellerName', 'FundAmount'],
                'sort_order' => 21,
            ],
            [
                'key' => 'greetings_to_client',
                'name' => 'Greetings To Client',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, আপনার ইউনিক আইডি: {ClientCode}, সার্ভার আইডি/আইপি: {UserName}',
                'placeholders' => ['CustomerName', 'ClientCode', 'UserName'],
                'sort_order' => 22,
            ],
            [
                'key' => 'employee_salary_payment',
                'name' => 'Employee Salary Payment',
                'event_key' => null,
                'body' => 'প্রিয় {EmployeeName}, আপনি পেয়েছেন {PaidAmount} টাকা। মাস: {MonthName}, মোট বেতন: {TotalSalary}, বকেয়া: {DueAmount}। {CompanyName}',
                'placeholders' => ['EmployeeName', 'PaidAmount', 'MonthName', 'TotalSalary', 'DueAmount', 'CompanyName'],
                'sort_order' => 23,
            ],
            [
                'key' => 'verification_code',
                'name' => 'Verification Code (OTP)',
                'event_key' => 'portal_otp',
                'body' => '{CompanyName}-তে আপনার OTP: {VerificationCode} — ১০ মিনিটের মধ্যে ব্যবহার করুন।',
                'placeholders' => ['CompanyName', 'VerificationCode', 'code', 'minutes'],
                'sort_order' => 24,
            ],
            [
                'key' => 'client_code_update',
                'name' => 'Client Code Update',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, ক্লায়েন্ট কোড পরিবর্তন হয়েছে! ব্যবহারকারী: {LoginUserName} পাসওয়ার্ড: {LoginPassword} URL: {BaseSiteURL} {CompanyName}',
                'placeholders' => ['CustomerName', 'LoginUserName', 'LoginPassword', 'BaseSiteURL', 'CompanyName'],
                'sort_order' => 25,
            ],
            [
                'key' => 'client_registration',
                'name' => 'Client Registration',
                'event_key' => null,
                'body' => 'আপনার লগইন: ব্যবহারকারী: {LoginUserName} পাসওয়ার্ড: {LoginPassword} URL: {BaseSiteURL} {CompanyName}',
                'placeholders' => ['LoginUserName', 'LoginPassword', 'BaseSiteURL', 'CompanyName'],
                'sort_order' => 26,
            ],
            [
                'key' => 'bandwidth_sale_receipt',
                'name' => 'Bandwidth Sale Receipt',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, {ReceiptAmount} টাকার ইনভয়েস {InvoiceId} এর রসিদ। {CompanyName} {CompanyMobile}',
                'placeholders' => ['CustomerName', 'ReceiptAmount', 'InvoiceId', 'CompanyName', 'CompanyMobile'],
                'sort_order' => 27,
            ],
            [
                'key' => 'bandwidth_sale_invoice',
                'name' => 'Bandwidth Sale Invoice',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, {Month} মাসের চালান বাকি। চালান: {InvoiceId} বকেয়া: {Due} শেষ তারিখ: {PaymentDue} {CompanyName}',
                'placeholders' => ['CustomerName', 'Month', 'InvoiceId', 'Due', 'PaymentDue', 'CompanyName'],
                'sort_order' => 28,
            ],
            [
                'key' => 'password_regenerator',
                'name' => 'Password Regenerator',
                'event_key' => null,
                'body' => 'প্রিয় {CustomerName}, পাসওয়ার্ড পুনরায় তৈরি হয়েছে। ব্যবহারকারী: {LoginUserName} পাসওয়ার্ড: {LoginPassword} ধন্যবাদ {CompanyName}',
                'placeholders' => ['CustomerName', 'LoginUserName', 'LoginPassword', 'CompanyName'],
                'sort_order' => 29,
            ],
            [
                'key' => 'new_client_request_employee',
                'name' => 'New Client Request (Employee)',
                'event_key' => null,
                'body' => 'হ্যালো {EmpName}, নতুন সংযোগ: নাম: {CustomerName} মোবাইল: {CustomerNumber} অঞ্চল: {Zone} ঠিকানা: {Address} প্যাকেজ: {Package} বিল: {MonthlyBillAmount} {CompanyName}',
                'placeholders' => ['EmpName', 'CustomerName', 'CustomerNumber', 'Zone', 'Address', 'Package', 'MonthlyBillAmount', 'CompanyName'],
                'sort_order' => 30,
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
