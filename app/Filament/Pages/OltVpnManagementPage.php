<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OltResource;
use App\Jobs\RunOltPptpDiagnoseJob;
use App\Models\Device;
use App\Support\OltManagementHelper;
use App\Support\Rbac\StaffCapability;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OltVpnManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.olt-vpn-management';

    protected static ?string $navigationLabel = 'OLT VPN / PPTP';

    protected static ?string $title = 'OLT VPN (PPTP & OpenVPN)';

    protected static ?string $slug = 'olt-vpn';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && StaffCapability::for($user)->canOlt();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->mountInteractsWithTable();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (OltResource::canCreate()) {
            $actions[] = Actions\Action::make('create_olt')
                ->label('Add new OLT')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(OltResource::getUrl('create'));
        }

        return $actions;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Device::query()
                    ->olts()
                    ->orderBy('display_name')
                    ->orderBy('management_ip'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('OLT')
                    ->searchable(['display_name', 'management_ip', 'serial_number'])
                    ->state(fn (Device $record): string => $record->adminLabel())
                    ->description(fn (Device $record): ?string => filled($record->display_name)
                        ? $record->management_ip
                        : null),
                Tables\Columns\TextColumn::make('vpn_active')
                    ->label('Active VPN')
                    ->badge()
                    ->state(fn (Device $record): string => OltManagementHelper::vpnType($record))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        OltManagementHelper::VPN_PPTP => 'PPTP',
                        OltManagementHelper::VPN_OPENVPN => 'OpenVPN',
                        default => 'None',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('vpn_material')
                    ->label('Configured')
                    ->state(function (Device $record): string {
                        $parts = [];
                        if (OltManagementHelper::openVpnConfigPath($record) !== null) {
                            $parts[] = '.ovpn';
                        }
                        if (OltManagementHelper::pptpConfigFromMeta($record) !== null) {
                            $parts[] = 'PPTP creds';
                        }

                        return $parts === [] ? '—' : implode(' + ', $parts);
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('pptp_server')
                    ->label('PPTP server')
                    ->state(function (Device $record): string {
                        $meta = is_array($record->meta) ? $record->meta : [];

                        return trim((string) ($meta[OltManagementHelper::META_PPTP_SERVER] ?? '')) ?: '—';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('route_subnet')
                    ->label('Route subnet')
                    ->state(function (Device $record): string {
                        $meta = is_array($record->meta) ? $record->meta : [];
                        $subnet = trim((string) ($meta[OltManagementHelper::META_PPTP_SUBNET] ?? ''));
                        if ($subnet !== '') {
                            return $subnet;
                        }

                        $ip = trim((string) ($record->management_ip ?? ''));

                        return $ip !== '' ? (OltManagementHelper::defaultPptpSubnet($ip) ?? '—') : '—';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vpn_filter')
                    ->label('VPN')
                    ->options([
                        'any' => 'Any VPN configured',
                        'active' => 'Active (PPTP or OpenVPN)',
                        'pptp' => 'Active PPTP',
                        'openvpn' => 'Active OpenVPN',
                        'none' => 'No active VPN',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($value): void {
                            $olts = Device::query()->olts()->get();
                            $ids = [];
                            foreach ($olts as $olt) {
                                $type = OltManagementHelper::vpnType($olt);
                                $hasMaterial = OltManagementHelper::hasVpnCompareData($olt);
                                $match = match ($value) {
                                    'active' => $type !== OltManagementHelper::VPN_NONE,
                                    'pptp' => $type === OltManagementHelper::VPN_PPTP,
                                    'openvpn' => $type === OltManagementHelper::VPN_OPENVPN,
                                    'none' => $type === OltManagementHelper::VPN_NONE,
                                    'any' => $hasMaterial,
                                    default => true,
                                };
                                if ($match) {
                                    $ids[] = $olt->id;
                                }
                            }
                            $q->whereIn('id', $ids !== [] ? $ids : [0]);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_vpn')
                    ->label('Manage')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Device $record): string => OltResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Device $record): bool => OltResource::canEdit($record)),
                Tables\Actions\Action::make('test_vpn')
                    ->label('Test VPN')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->action(function (Device $record): void {
                        RunOltPptpDiagnoseJob::dispatch((int) $record->id);
                        Notification::make()
                            ->title('VPN test started — '.$record->adminLabel())
                            ->body('ব্যাকগ্রাউন্ডে চলছে। ৩০–৯০ সেক পর আবার **Test VPN** বা Edit-এ **VPN result**।')
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('vpn_result')
                    ->label('Result')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->action(function (Device $record): void {
                        $cached = \Illuminate\Support\Facades\Cache::get(RunOltPptpDiagnoseJob::cacheKey((int) $record->id));
                        if (! is_array($cached) || ($cached['status'] ?? '') === 'running') {
                            Notification::make()
                                ->title(($cached['status'] ?? '') === 'running' ? 'Still running…' : 'No result yet')
                                ->body('আগে **Test VPN** চাপুন, একটু অপেক্ষা করুন।')
                                ->warning()
                                ->send();

                            return;
                        }
                        $body = ($cached['summary'] ?? '')."\n\n".implode("\n", $cached['lines'] ?? []);
                        $ok = (bool) ($cached['success'] ?? false);
                        $n = Notification::make()
                            ->title($ok ? 'Reachable' : 'Not reachable')
                            ->body($body)
                            ->persistent();
                        $ok ? $n->success() : $n->danger();
                        $n->send();
                    }),
            ])
            ->emptyStateHeading('No OLTs')
            ->emptyStateDescription('Add an OLT from OLT list, then configure VPN here.')
            ->paginated([10, 25, 50]);
    }

    public function getEgressIpHint(): string
    {
        $ip = trim((string) config('app.server_egress_ip', env('APP_SERVER_EGRESS_IP', '')));

        return $ip !== '' ? $ip : '—';
    }
}
