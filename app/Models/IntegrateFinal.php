<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrateFinal extends Model
{
    protected $table = "integrate_final";
    use HasFactory;

    protected $dates = ['adate','dob'];
}
