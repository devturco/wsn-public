<?php

namespace App\Models\Car;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'name',
        'image',
        'slug',
        'status',
        'serial_number',
    ];

    public function car_contents()
    {
        return $this->hasMany(CarContent::class);
    }
}
