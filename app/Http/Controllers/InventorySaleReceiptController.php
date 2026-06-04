<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesInventorySaleAccess;
use App\Models\InventorySale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InventorySaleReceiptController extends Controller
{
    use AuthorizesInventorySaleAccess;

    public function show(Request $request, InventorySale $sale): View
    {
        $sale = $this->authorizeInventorySale($sale);

        return view('inventory.sale-receipt', array_merge(
            $this->saleReceiptViewData($sale),
            ['autoPrint' => $request->boolean('print')],
        ));
    }

    public function pdf(InventorySale $sale): Response
    {
        $sale = $this->authorizeInventorySale($sale);

        $html = view('inventory.sale-receipt-pdf', $this->saleReceiptViewData($sale))->render();

        $tmpDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmpDir,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->WriteHTML($html);

        $filename = str_replace(['/', '\\'], '-', $sale->sale_number).'.pdf';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
