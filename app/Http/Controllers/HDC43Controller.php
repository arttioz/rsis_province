<?php

namespace App\Http\Controllers;

use App\Imports\HDC43_import;
use App\Models\hdc_43;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class HDC43Controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $hdc_43 = hdc_43::orderby('created_at', 'desc')
            ->get();
        return view('uploads.hdc43');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }


    public function upload(Request $request)
    {
        //
        // upload laravel excel

        // check month
        $year_month = $request->year . '-' . $request->month;

        // delete old data by month
        $hdc_43_del = hdc_43::find(1);
        $hdc_43_del->delete();

        Excel::import(new HDC43_import, 'hdc43_' . $year_month . '.xlsx');

        return redirect('/upload43')->with('success', 'All good!');


        //

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\hdc_43  $hdc_43
     * @return \Illuminate\Http\Response
     */
    public function show(hdc_43 $hdc_43)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\hdc_43  $hdc_43
     * @return \Illuminate\Http\Response
     */
    public function edit(hdc_43 $hdc_43)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\hdc_43  $hdc_43
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, hdc_43 $hdc_43)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\hdc_43  $hdc_43
     * @return \Illuminate\Http\Response
     */
    public function destroy(hdc_43 $hdc_43)
    {
        //
    }
}
