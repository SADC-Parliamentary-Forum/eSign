<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment('local')) {
        return redirect(config('app.frontend_url'));
    }

    return view('welcome');
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
