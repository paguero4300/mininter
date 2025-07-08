<?php

use Illuminate\Support\Facades\Route;

// Redireccionar automáticamente a panel de administración
Route::get('/', function () {
    return redirect('/admin/login');
});
