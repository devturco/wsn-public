<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'language_id',
        'name',
        'shop_name',
        'country',
        'city',
        'state',
        'zip_code',
        'address',
        'details'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
