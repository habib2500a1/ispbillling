<?php

namespace Tests\Feature;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPayTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_page_is_public(): void
    {
        $this->get('/pay')->assertOk()->assertSee('Pay your bill');
    }

    public function test_user_id_opens_bill_page(): void
    {
        CustomersInfo::create([
            'customer_unique_id' => 'FCNET100',
            'customer_name' => 'Habib',
            'mobile' => '8801841558023',
            'status' => 'active',
        ]);
        BillingInfo::create([
            'customer_bill_unique_id' => 'FCNET100',
            'monthly_rent' => 500,
            'due_amount' => 500,
        ]);

        $this->get('/pay/FCNET100')
            ->assertOk()
            ->assertSee('FCNET100')
            ->assertSee('Habib')
            ->assertSee('500');
    }

    public function test_unknown_id_returns_to_lookup(): void
    {
        $this->get('/pay/NO-SUCH-ID')->assertRedirect(route('pay.lookup'));
    }
}
