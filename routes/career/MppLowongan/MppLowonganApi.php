<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Career\MppLowongan\MppLowonganApi;

Route::prefix('api')->group(function () {
    Route::apiResource('mpp-lowongan', MppLowonganApi::class)->names('api.mpp-lowongan');
});
