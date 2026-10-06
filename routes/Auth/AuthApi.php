<?php

use App\Http\Controllers\Auth\AuthApi;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::apiResource('auth', AuthApi::class)->names('api.auth');
});
