<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalFnbItem extends Model
{
    use HasFactory;

    protected $table = 'rental_fnb_items';

    protected $fillable = ['rental_id', 'fnb_item_id', 'qty', 'unit_price', 'subtotal', 'addons', 'addons_price'];

    protected $casts = [
        'unit_price'   => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'addons_price' => 'decimal:2',
        'addons'       => 'array',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function fnbItem(): BelongsTo
    {
        return $this->belongsTo(FnbItem::class);
    }
}
