<?php

namespace Themes\Golf\Agent;

use Modules\ModuleServiceProvider;
use Modules\User\Helpers\PermissionHelper;

class ModuleProvider extends ModuleServiceProvider
{

    public function boot(){
        PermissionHelper::add("agent_booking");
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
    }

    public static function getUserMenu()
    {
        $res =  [];
        if(auth()->user()->hasPermission('agent_booking')){
            $res['agent_dashboard'] = [
                "position"=>10,
                'url'        => route('user.agent.dashboard'),
                'title'      => __("Dashboard"),
                'icon'       => 'icon ion-md-card',
                'permission' => 'agent_booking',
            ];
        }
        return $res;
    }
}
