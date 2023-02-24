<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoliceData extends Model
{
    protected $table = "police_vehicle_final";
    use HasFactory;
    protected $dates = ['adate'];
    protected $fillable = ["match","is_duplicate"];
}
