<?php

use Illuminate\Support\Facades\Route;

Route::get('/buzzsound', function () {
    return view('buzzsound');
});