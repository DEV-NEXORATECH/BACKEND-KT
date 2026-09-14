<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\RoleMenuController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/rbac/me', [RbacController::class, 'me']);
    Route::apiResource('/menus', MenuController::class)->except(['show']);
    Route::get('/roles/menus', [RoleMenuController::class, 'index']);
    Route::put('/roles/{role}/menus', [RoleMenuController::class, 'sync']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
