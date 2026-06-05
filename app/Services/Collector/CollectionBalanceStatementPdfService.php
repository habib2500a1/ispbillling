<?php

namespace App\Services\Collector;

use App\Support\CompanyBranding;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class CollectionBalanceStatementPdfService
{
    /**
     * @param  array{summary: array<string, mixed>, staff: list<array<string, mixed>>}  $statement
     */
    public function download(array $statement, string $dateFrom, string $dateTo, bool $inline = false): Response
    {
        $filename = sprintf(
            'collection-balance-statement_%s_%s.pdf',
            $dateFrom,
            $dateTo,
        );

        $html = view('filament.pdf.collection-balance-statement', [
            'statement' => $statement,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'generatedAt' => now()->format('d M Y, h:i A'),
            'letterhead' => $this->letterhead(),
        ])->render();

        return $this->pdfResponse($html, $filename, $inline);
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
