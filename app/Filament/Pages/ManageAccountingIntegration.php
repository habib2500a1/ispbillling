<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

/**
 * @property Form $form
 */
class ManageAccountingIntegration extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.manage-accounting-integration';

    protected static ?string $navigationLabel = 'GL auto-post';

    protected static ?string $title = 'Accounting GL integration';

    protected static ?string $navigationGroup = 'Accounting';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin']) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'auto_post_payments' => (bool) config('accounting.auto_post_customer_payments', true),
            'auto_post_invoices' => (bool) config('accounting.auto_post_invoices', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('General ledger auto-post')
                            ->description('Creates journal entries when payments complete or invoices are issued.')
                            ->schema([
                                Toggle::make('auto_post_payments')
                                    ->label('Auto-post customer payments'),
                                Toggle::make('auto_post_invoices')
                                    ->label('Auto-post issued invoices (AR + revenue + VAT)'),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        AppSetting::putValue('accounting.auto_post_customer_payments', ($state['auto_post_payments'] ?? false) ? '1' : '0');
        AppSetting::putValue('accounting.auto_post_invoices', ($state['auto_post_invoices'] ?? false) ? '1' : '0');
        AppSetting::syncToRuntimeConfig();

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Accounting GL settings updated',
                'context' => ['tenant_id' => TenantResolver::currentTenantId()],
            ]);
        } catch (\Throwable $e) {
            Log::warning('accounting_settings.audit_failed', ['error' => $e->getMessage()]);
        }

        Notification::make()->title('Accounting settings saved')->success()->send();
    }
}
