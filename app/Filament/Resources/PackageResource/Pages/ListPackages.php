<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFromMikrotik')
                ->label('Sync from MikroTik')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled(fn (): bool => MikrotikServer::query()->where('is_enabled', true)->doesntExist())
                ->tooltip(fn (): ?string => MikrotikServer::query()->where('is_enabled', true)->doesntExist()
                    ? 'কোনো চালু MikroTik সার্ভার নেই — আগে Network → MikroTik এ যোগ করুন।'
                    : null)
                ->modalHeading('Import PPP profiles as packages')
                ->modalDescription('রাউটারের PPP profile ইমপোর্ট হবে — Package নাম আপনি পরে দেবেন (যেমন 25 Mbps)। Profile নাম আলাদা ফিল্ডে থাকবে। নতুন ইমপোর্ট inactive থাকবে যতক্ষণ নাম/দাম সেট করেন।')
                ->form([
                    Forms\Components\Select::make('mikrotik_server_id')
                        ->label('MikroTik server')
                        ->options(fn (): array => MikrotikServer::query()
                            ->where('is_enabled', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (MikrotikServer $s): array => [
                                $s->id => $s->name.' ('.$s->host.')',
                            ])
                            ->all())
                        ->required()
                        ->helperText('শুধো চালু (Enabled) সার্ভার।'),
                ])
                ->action(function (array $data): void {
                    $server = MikrotikServer::query()->findOrFail((int) $data['mikrotik_server_id']);
                    $result = app(MikrotikServerService::class)->syncPackagesFromPppProfiles($server->fresh());
                    $errSample = array_slice($result['errors'], 0, 5);
                    $body = sprintf(
                        'Created: %d, updated: %d, skipped: %d.',
                        $result['created'],
                        $result['updated'],
                        $result['skipped'],
                    );
                    if ($errSample !== []) {
                        $body .= ' Errors: '.implode(' | ', $errSample);
                    }
                    $notification = Notification::make()
                        ->title('MikroTik package sync done')
                        ->body($body);
                    if ($result['errors'] !== []) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }
                    $notification->send();
                }),
            Actions\Action::make('cleanupMikrotikImports')
                ->label('Delete unused MT imports')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete unused MikroTik-imported packages?')
                ->modalDescription('শুধু সেগুলো মুছবে যেগুলো MikroTik সিঙ্ক থেকে এসেছে, নাম = profile নাম, এবং কোনো subscriber নেই।')
                ->action(function (): void {
                    $deleted = \App\Models\Package::query()
                        ->whereNotNull('mikrotik_synced_at')
                        ->whereColumn('name', 'mikrotik_profile_name')
                        ->whereDoesntHave('customers')
                        ->delete();

                    Notification::make()
                        ->title('Cleanup done')
                        ->body("Deleted {$deleted} unused package(s).")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
