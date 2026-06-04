<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Support\CompanyBranding;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class ResellerCommissionPdfService
{
    public function __construct(
        private readonly ResellerReportService $reports,
    ) {}

    public function periodStatementResponse(
        Reseller $reseller,
        Carbon $from,
        Carbon $to,
        ?string $status = null,
        bool $inline = false,
    ): Response {
        $payload = $this->periodPayload($reseller, $from, $to, $status);
        $filename = sprintf(
            'commission-statement-%s-%s-%s.pdf',
            $reseller->code,
            $from->format('Ymd'),
            $to->format('Ymd'),
        );

        return $this->pdfResponse(
            view('reseller.pdf.commission-statement', $payload)->render(),
            $filename,
            $inline,
        );
    }

    public function singleCommissionResponse(
        Reseller $reseller,
        ResellerCommission $commission,
        bool $inline = true,
    ): Response {
        abort_unless((int) $commission->reseller_id === (int) $reseller->id, 403);

        $commission->load(['customer:id,name,customer_code', 'payment:id,amount,paid_at,method,receipt_number']);

        $filename = 'commission-'.$commission->id.'.pdf';

        return $this->pdfResponse(
            view('reseller.pdf.commission-single', [
                'reseller' => $reseller,
                'commission' => $commission,
                'letterhead' => $this->letterhead(),
            ])->render(),
            $filename,
            $inline,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function periodPayload(Reseller $reseller, Carbon $from, Carbon $to, ?string $status = null): array
    {
        $summary = $this->reports->summary($from, $to, $reseller->id, $reseller->tenant_id);

        $query = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('earned_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('earned_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->with(['customer:id,name,customer_code', 'payment:id,amount,paid_at,method'])
            ->orderByDesc('earned_at');

        if ($status !== null && in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $rows = $query->limit(500)->get();

        $filteredTotal = (float) $rows->sum('commission_amount');
        $filteredPending = (float) $rows->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount');
        $filteredPaid = (float) $rows->where('status', ResellerCommission::STATUS_PAID)->sum('commission_amount');

        return [
            'reseller' => $reseller,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'statusFilter' => $status,
            'summary' => $summary,
            'filteredTotal' => round($filteredTotal, 2),
            'filteredPending' => round($filteredPending, 2),
            'filteredPaid' => round($filteredPaid, 2),
            'rows' => $rows,
            'generatedAt' => now(),
            'letterhead' => $this->letterhead(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterhead(): array
    {
        return [
            'name' => CompanyBranding::name(),
            'tagline' => CompanyBranding::tagline(),
            'address' => CompanyBranding::address(),
            'phone' => CompanyBranding::phone(),
            'email' => CompanyBranding::email(),
            'logoPath' => CompanyBranding::logoAbsolutePath(),
            'showLogo' => CompanyBranding::invoiceShowLogo() && CompanyBranding::logoAbsolutePath() !== null,
            'footer' => CompanyBranding::invoiceFooter(),
        ];
    }

    private function pdfResponse(string $html, string $filename, bool $inline): Response
    {
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

        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }
}
