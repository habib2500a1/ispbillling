<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Models\Customer;
use App\Services\Subscribers\CustomerLineActivationService;
use Filament\Notifications\Notification;

trait ActivatesSubscriberLine
{
    /**
     * @param  array<string, mixed>  $formState
     */
    protected function runLineActivationAfterRegister(Customer $customer, array $formState): void
    {
        $service = app(CustomerLineActivationService::class);

        if (! $service->shouldActivateFromRegisterForm($formState)) {
            return;
        }

        try {
            $result = $service->activate($customer, $service->inputFromRegisterForm($formState));

            Notification::make()
                ->title('লাইন চার্জ প্রয়োগ হয়েছে')
                ->body($result['message'])
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('লাইন চার্জ প্রয়োগ হয়নি')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->warning()
                ->send();
        }
    }
}
