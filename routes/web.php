<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StateController;

Route::get('/', function () {
    return view('map');
});

Route::get('/api/states', [StateController::class, 'index']);
