<?php

namespace App\Livewire;

use App\Services\Billing\CustomerExcelImporter;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\SimpleExcel\SimpleExcelWriter;

class CustomerExcelUpload extends Component
{
    use WithFileUploads;

    public $file = null;

    /** @var array{created: int, updated: int, skipped: int, errors: list<string>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        if (! $this->canUpload()) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function downloadDemo()
    {
        if (! $this->canUpload()) {
            abort(403);
        }

        $path = $this->writableTempPath('anetbd-user-upload-demo.xlsx');
        if (is_file($path)) {
            @unlink($path);
        }

        $writer = SimpleExcelWriter::create($path);
        foreach (CustomerExcelImporter::demoRows() as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        return response()->download($path, 'anetbd-user-upload-demo.xlsx')->deleteFileAfterSend(true);
    }

    public function import(): void
    {
        if (! $this->canUpload()) {
            abort(403);
        }

        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $ext = strtolower($this->file->getClientOriginalExtension() ?: 'xlsx');
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            $ext = 'csv';
        }

        $safe = $this->writableTempPath('upload-'.uniqid('', true).'.'.$ext);
        copy($this->file->getRealPath(), $safe);

        try {
            $this->result = app(CustomerExcelImporter::class)->import($safe);
        } finally {
            @unlink($safe);
        }

        $created = $this->result['created'] ?? 0;
        $updated = $this->result['updated'] ?? 0;
        $skipped = $this->result['skipped'] ?? 0;

        if ($created + $updated > 0) {
            flash()->success(__(':created created, :updated updated, :skipped skipped.', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ]));
        } else {
            flash()->warning(__('No users saved. :skipped rows skipped. Check the demo columns.', [
                'skipped' => $skipped,
            ]));
        }

        $this->file = null;
    }

    public function render()
    {
        return view('livewire.customer-excel-upload', [
            'headers' => CustomerExcelImporter::headers(),
        ])->layout('layouts.app');
    }

    private function canUpload(): bool
    {
        return hasAccess(
            ['Super Admin', 'Operator', 'Reseller'],
            ['new-customer', 'all-customer', 'new-subscriber', 'all-subscribers']
        );
    }

    private function writableTempPath(string $filename): string
    {
        $dir = storage_path('app/tmp');
        try {
            File::ensureDirectoryExists($dir, 0775);
            @chmod($dir, 0775);
        } catch (\Throwable) {
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        }

        $probe = $dir.DIRECTORY_SEPARATOR.'.write-test';
        if (@file_put_contents($probe, 'ok') === false) {
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        } else {
            @unlink($probe);
        }

        return $dir.DIRECTORY_SEPARATOR.$filename;
    }
}
