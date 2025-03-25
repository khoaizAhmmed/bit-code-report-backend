<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
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

Route::apiResource('members', MemberController::class);
// Route::apiResource('reports', ReportController::class);

Route::get('/', function () {
    return 'welcome';
});
