<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — PROJECT: WEB CAREER
|--------------------------------------------------------------------------
| Fitur legacy dihapus. Sisakan endpoint minimal.
*/

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

// Pemicu tugas terjadwal (Cloud Scheduler → antrean Cloud Tasks).
require base_path('routes/career/Tugas/PemicuTugasApi.php');
