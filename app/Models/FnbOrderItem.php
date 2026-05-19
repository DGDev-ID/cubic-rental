<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FnbOrderItem extends Model
{
    protected $fillable = [
        'fnb_order_id', 'fnb_item_id', 'qty',
        'unit_price', 'subtotal', 'addons', 'addons_price',
    ];

    protected $casts = [
        'unit_price'   => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'addons_price' => 'decimal:2',
        'addons'       => 'array',
    ];

    public function fnbOrder(): BelongsTo
    {
        return $this->belongsTo(FnbOrder::class);
    }

    public function fnbItem(): BelongsTo
    {
        return $this->belongsTo(FnbItem::class);
    }
}
