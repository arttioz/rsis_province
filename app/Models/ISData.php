<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ISData extends Model
{
    protected $table = "is_clean_final";
    use HasFactory;

    protected $dates = ["hdate","adate","birth"];
    protected $fillable = ["match","is_duplicate"];
}
