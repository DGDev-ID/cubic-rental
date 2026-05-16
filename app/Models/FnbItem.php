<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FnbItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fnb_items';

    protected $fillable = ['name', 'category', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
