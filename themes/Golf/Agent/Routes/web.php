<?php
use Illuminate\Support\Facades\Route;

Route::prefix('/user/agent')->name('user.agent.')->middleware(['web','auth'])->group(function(){
   Route::get('/dashboard',[\Themes\Golf\Agent\Controllers\DashboardController::class,'index'])->name('dashboard');
   Route::get('/reloadChart',[\Themes\Golf\Agent\Controllers\DashboardController::class,'reloadChart'])->name('reloadChart');
});
