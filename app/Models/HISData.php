<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HISData extends Model
{
    protected $table = "his_query_clean_final";
    use HasFactory;

    protected $dates = ["DATE_SERV","BIRTH"];
    protected $fillable = ["match","is_duplicate"];
}
