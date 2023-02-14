<?php

namespace App\Imports;

use App\Models\hdc_43;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;

class HDC43_import implements ToModel
{

    public function model(array $row)
    {

        return new hdc_43([
            'cid'     => $row[0],
            'fname'    => $row[1],
            'lname' => $row[2],
            'lname' => $row[3],
            'age' => $row[4],
            'bd' => $row[5],
            'date_serv' => $row[6],
        ]);
    }
}
