<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeDocument;
use App\Support\PrimaryTenant;
use Illuminate\Database\Seeder;

class AiKnowledgeDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = PrimaryTenant::id();
        $docs = [
            [
                'category' => 'sop',
                'title' => 'ধীর ইন্টারনেট — প্রথম ধাপ',
                'content' => 'গ্রাহককে ONU/রাউটার রিবুট করতে বলুন। PPP সেশন চেক করুন। RX সিগন্যাল -27 dBm এর নিচে হলে ফিল্ড ভিজিট লাগতে পারে।',
                'locale' => 'bn',
            ],
            [
                'category' => 'sop',
                'title' => 'Slow internet — first steps',
                'content' => 'Ask the customer to reboot ONU/router. Check PPP session. If RX is below -27 dBm, schedule a field visit.',
                'locale' => 'en',
            ],
            [
                'category' => 'billing',
                'title' => 'বিল ও পেমেন্ট নীতি',
                'content' => 'খোলা ইনভয়েস অ্যাপের Bills ট্যাব বা /pay লিংক থেকে পরিশোধ করা যায়। বকেয়া থাকলে সেবা সাসপেন্ড হতে পারে।',
                'locale' => 'bn',
            ],
        ];

        foreach ($docs as $doc) {
            AiKnowledgeDocument::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'title' => $doc['title'],
                ],
                array_merge($doc, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]),
            );
        }
    }
}
