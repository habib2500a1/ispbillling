<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    use BelongsToTenant;

    public const TYPE_VENDOR = 'vendor';

    public const TYPE_GENERAL = 'general';

    protected $fillable = [
        'tenant_id',
        'expense_type',
        'vendor_id',
        'expense_category',
        'payee_name',
        'payment_date',
        'amount',
        'vat_amount',
        'payment_method',
        'bank_account_id',
        'reference',
        'status',
        'notes',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isVendorExpense(): bool
    {
        return ($this->expense_type ?? self::TYPE_VENDOR) === self::TYPE_VENDOR;
    }

    public function typeLabel(): string
    {
        return config('vendor_expenses.types.'.$this->expense_type, ucfirst((string) $this->expense_type));
    }

    public function categoryLabel(): ?string
    {
        if (! filled($this->expense_category)) {
            return null;
        }

        return config('vendor_expenses.general_categories.'.$this->expense_category, $this->expense_category);
    }

    public function displayName(): string
    {
        if ($this->isVendorExpense()) {
            return $this->vendor?->name ?? 'Vendor';
        }

        $category = $this->categoryLabel() ?? 'General expense';

        if (filled($this->payee_name)) {
            return $this->payee_name.' · '.$category;
        }

        return $category;
    }
}
