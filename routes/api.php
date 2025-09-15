<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::apiResource('reports', ReportController::class);
Route::apiResource('members', MemberController::class);

Route::get('members/{member}/reports', [ReportController::class, 'getReportsByMember']);
Route::get('all-reports', [ReportController::class, 'getYearlyAttendanceReport']);

Route::get('members/{member}', [MemberController::class, 'show']);


// Route::get('members/{member}/reports/{month}', [ReportController::class, 'getReportsByMemberAndMonth']);
Route::get('members/{member}/reports/{year}/{month}', [ReportController::class, 'getReportsByMemberAndMonth']);

Route::get('members/{member}/reports/{year}', [ReportController::class, 'getReportsByMemberAndYear']);




Route::get('/test-cors', function (Request $request) {
    return response()->json(['message' => 'CORS is working!']);
});




Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
