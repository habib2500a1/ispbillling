<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Resources\ResellerResource;
use App\Services\Resellers\ResellerPortalAccessService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditReseller extends EditRecord
{
    protected static string $resource = ResellerResource::class;

    protected ?string $plainPortalPassword = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['portal_password'] = null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['portal_password'] ?? null)) {
            $this->plainPortalPassword = (string) $data['portal_password'];
            $data['portal_password'] = Hash::make($this->plainPortalPassword);
        } else {
            unset($data['portal_password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->plainPortalPassword !== null) {
            app(ResellerPortalAccessService::class)->storePlainPasswordIfKnown(
                $this->record,
                $this->plainPortalPassword,
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reseller_portal_login')
                ->label('Portal login')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->url(fn (): string => route('staff.resellers.portal-login', ['reseller' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            Actions\Action::make('reseller_portal_credentials')
                ->label('Portal ID & password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->modalHeading(fn (): string => 'Portal access — '.$this->record->name)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (): \Illuminate\Contracts\View\View {
                    $record = $this->record;
                    $portal = app(ResellerPortalAccessService::class);
                    $portal->ensurePortalPassword($record);
                    $fresh = $record->fresh() ?? $record;

                    return view('filament.resources.reseller-resource.portal-access-modal', [
                        'login' => $portal->portalLoginId($fresh),
                        'passwordPlain' => $portal->portalPasswordPlain($fresh),
                        'token' => $portal->ensureAccessToken($fresh),
                        'link' => $portal->accessTokenUrl($fresh),
                    ]);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
