<?php

namespace App\Models\HomePage;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterInformation extends Model
{
  use HasFactory;

  protected $table = 'counter_informations';

  /**
   * The attributes that aren't mass assignable.
   *
   * @var array
   */
  protected $guarded = [];

  public function language()
  {
    return $this->belongsTo(Language::class, 'language_id', 'id');
  }
}
