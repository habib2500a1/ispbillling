<?php

namespace App\Filament\Concerns;

/**
 * Validates Livewire inline forms without overriding Filament\Pages\BasePage::validate().
 */
trait ValidatesInlineForm
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validatedFormPayload(array $rules): array
    {
        return validator(['form' => $this->form], $rules)->validate()['form'];
    }
}
