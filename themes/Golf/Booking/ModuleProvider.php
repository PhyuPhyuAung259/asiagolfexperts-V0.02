<?php

namespace Themes\Golf\Booking;

use Modules\Booking\Controllers\BookingController;
use Modules\Booking\Models\Booking;
use Modules\ModuleServiceProvider;

class ModuleProvider extends ModuleServiceProvider
{

    public function boot(){
        $this->app->bind(Booking::class,\Themes\Golf\Booking\Models\Booking::class);
        $this->app->bind(BookingController::class,\Themes\Golf\Booking\Controllers\BookingController::class);
    }
}
