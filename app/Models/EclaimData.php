<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EclaimData extends Model
{
    protected $table = "eclaim_clean_final";
    use HasFactory;

    protected $dates = ["adate","birthdate"];
    protected $fillable = ["match","is_duplicate"];
}
