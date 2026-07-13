<?php

use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|storage).*$');
