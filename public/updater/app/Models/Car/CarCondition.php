<?php

namespace App\Models\Car;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarCondition extends Model
{
    use HasFactory;
    protected $fillable = [
        'language_id',
        'name',
        'slug',
        'status',
        'serial_number',
    ];
}
