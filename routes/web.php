<?php

use App\Http\Controllers\ProcessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/merge/{startData}/{endDate}', [ProcessController::class, 'mergeRSIS']);

Route::get('/check/duplicate/{startData}/{endDate}', [ProcessController::class, 'checkDuplicate']);

Route::get('/prepare/police/{month}', [ProcessController::class, 'preparePoliceData']);
