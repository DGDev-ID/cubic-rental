<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FnbAddon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fnb_addons';

    protected $fillable = ['name', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
