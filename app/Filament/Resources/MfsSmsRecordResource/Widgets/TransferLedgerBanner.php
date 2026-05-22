<?php

namespace App\Filament\Resources\MfsSmsRecordResource\Widgets;

use Filament\Widgets\Widget;

class TransferLedgerBanner extends Widget
{
    protected static string $view = 'filament.widgets.mfs-transfer-ledger-banner';

    protected int|string|array $columnSpan = 'full';
}
