<?php

namespace Themes\Golf\Agent;

use Modules\Core\Abstracts\BaseSettingsClass;

class SettingClass extends BaseSettingsClass
{
    public static function getSettingPages()
    {
        return [
            [
                'id'   => 'agent',
                'title' => __("Agent Settings"),
                'position'=>20,
                'view'=>"Agent::admin.settings.agent",
                "keys"=>[
                    'agent_commission_amount',
                    'agent_commission_type',
                ],
                'html_keys'=>[

                ]
            ]
        ];
    }
}

