<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    Route::get('/invite', [InviteController::class,'validateToken']);
});
    
Route::middleware('auth:api')->group(function () {
    Route::prefix('/auth')
        ->controller(AuthController::class)
        ->group(function () {
            Route::get('/user', 'user');
            Route::post('/logout', 'logout');
        });

    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->middleware('can:users.viewAny');
        Route::post('/users', 'store')->middleware('can:users.create');
        Route::get('/users/{id}', 'show')->middleware('can:users.view');
        Route::put('/users/{id}', 'update')->middleware('can:users.update');
        Route::delete('/users/{id}', 'delete')->middleware('can:users.delete');
    });
});