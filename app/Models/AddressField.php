<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddressField extends Model
{
    use BelongsToSaasOperator;
    use HasFactory;

    protected $fillable = [
        'saas_operator_id',
        'label',
        'input_type',
        'dropdown_list',
        'required',
        'print_preview',
        'complain_preview',
        'order',
        'receipt_order',
    ];
}
