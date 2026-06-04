<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        if ($response->isRedirect()) {
            $response->assertRedirect();
            $this->assertTrue(
                str_contains((string) $response->headers->get('Location'), 'pay')
                || str_contains((string) $response->headers->get('Location'), 'bill-payment'),
            );

            return;
        }

        $response->assertOk();
    }
}
