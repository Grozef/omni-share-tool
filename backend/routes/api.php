<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\PublicationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// Public portfolio routes (no auth)
Route::get('/portfolio/{slug}',       [PortfolioController::class, 'publicShow']);
Route::get('/portfolio/{slug}/feed',  [PortfolioController::class, 'publicFeed']);

// Authenticated routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/logout',  [AuthController::class, 'logout']);
    Route::get('/auth/me',       [AuthController::class, 'me']);

    // Platforms
    Route::get('/platforms',                         [PlatformController::class, 'index']);
    Route::get('/platforms/{platform}/connect',      [PlatformController::class, 'connect']);
    Route::get('/platforms/{platform}/callback',     [PlatformController::class, 'callback']);
    Route::delete('/platforms/{platform}',           [PlatformController::class, 'destroy']);

    // Publications
    Route::get('/publications',              [PublicationController::class, 'index']);
    Route::post('/publications',             [PublicationController::class, 'store']);
    Route::get('/publications/{id}',         [PublicationController::class, 'show']);
    Route::post('/publications/{id}/retry',  [PublicationController::class, 'retry']);
    Route::delete('/publications/{id}',      [PublicationController::class, 'destroy']);

    // Media
    Route::post('/media/upload',               [MediaController::class, 'upload']);
    Route::post('/media/{id}/convert-video',   [MediaController::class, 'convertToVideo']);
    Route::get('/media/{id}/preview',          [MediaController::class, 'preview']);

    // Portfolio (authenticated management)
    Route::get('/portfolio',                    [PortfolioController::class, 'show']);
    Route::put('/portfolio',                    [PortfolioController::class, 'update']);
    Route::post('/portfolio/widget-token',      [PortfolioController::class, 'widgetToken']);
});
