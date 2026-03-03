<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookingController;

Route::get('/seed-check', [BookingController::class, 'seedCheck']);
Route::get('/availability', [BookingController::class, 'availability']);
Route::post('/book', [BookingController::class, 'book']);