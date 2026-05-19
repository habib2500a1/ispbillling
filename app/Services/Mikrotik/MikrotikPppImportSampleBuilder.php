<?php

namespace App\Services\Mikrotik;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class MikrotikPppImportSampleBuilder
{
    public const FILENAME = 'mikrotik-ppp-subscribers-sample.xlsx';

    /**
     * Column guide shown on the Instructions sheet.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function columnGuide(): array
    {
        return [
            ['username', 'Required. PPP secret / login (same as RouterOS secret name).'],
            ['password', 'PPPoE password (optional if set on customer later).'],
            ['profile', 'RouterOS PPP profile — maps to package if configured.'],
            ['name', 'Subscriber display name.'],
            ['phone', 'Mobile number (for SMS / bill pay).'],
            ['customer_code', 'Leave blank to auto-generate, or set existing code to update.'],
            ['disabled', 'yes/no — RouterOS secret disabled state.'],
        ];
    }

    public function buildBinary(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Subscribers');

        $headers = array_column(self::columnGuide(), 0);
        $sheet->fromArray($headers, null, 'A1');

        $sheet->fromArray([
            ['user001', 'pass123', '10M', 'Rahim Uddin', '01712345678', '', 'no'],
            ['user002', 'pass456', '20M', 'Karim Ahmed', '01812345678', '', 'no'],
            ['user003', 'pass789', '5M', 'Sample Client', '01912345678', 'TST0001', 'no'],
        ], null, 'A2');

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Instructions');
        $guide->setCellValue('A1', 'MikroTik PPP subscriber import — fill the Subscribers sheet');
        $guide->getStyle('A1')->getFont()->setBold(true);
        $guide->fromArray([
            ['Column', 'Description'],
            ...self::columnGuide(),
            ['', ''],
            ['Steps', ''],
            ['1', 'Download this file and fill rows on the Subscribers sheet.'],
            ['2', 'Admin → Network → MikroTik → Upload Excel/CSV.'],
            ['3', 'Choose the target router, upload file, then Save.'],
            ['4', 'Subscribers are created/updated and linked to that MikroTik server.'],
        ], null, 'A2');
        $guide->getColumnDimension('A')->setWidth(18);
        $guide->getColumnDimension('B')->setWidth(70);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }
}
