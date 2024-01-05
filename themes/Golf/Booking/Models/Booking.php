<?php

namespace Themes\Golf\Booking\Models;

use App\User;

class Booking extends \Modules\Booking\Models\Booking
{

    public function calculateCommission(){
        $data = $this->getCommissionVendor();

        $this->commission = $data['commission'];
        $this->commission_type = $data['commission_type'];


        $data = $this->getCommissionAgent();
        $this->agent_com_amount = $data['commission'];
        $this->agent_com_type = $data['commission_type'];
    }


    public function getCommissionAgent(){
        $agentId = $this->customer_id;
        $total = $this->total_before_fees;
        $returnArray=[
            'commission'=>0,
            'commission_type'=>'',
        ];
        $agent = User::find($agentId);
        if (!empty($agent)) {
            $commission = [];
            $commission['amount'] = setting_item('agent_commission_amount', 10);
            $commission['type'] = setting_item('agent_commission_type', 'percent');

            if($agent->vendor_commission_type){
                $commission['type'] = $agent->vendor_commission_type;
            }
            if($agent->vendor_commission_amount){
                $commission['amount'] = $agent->vendor_commission_amount;
            }

            if($commission['type'] == 'disable'){
                return $returnArray;
            }

            if ($commission['type'] == 'percent') {
                $returnArray['commission'] = (float)($total / 100) * $commission['amount'];
            } else {
                $returnArray['commission']= (float)min($total,$commission['amount']);
            }
            $returnArray['commission_type'] = json_encode($commission);
        }
        return $returnArray;
    }

    public function customer(){
        return $this->belongsTo(User::class,'customer_id');
    }


    public static function getTopCardsReportForAgent($user_id)
    {

        $res = [];
        $total_money = parent::selectRaw('sum( `agent_com_amount` ) AS total_price , sum( CASE WHEN `status` = "completed" THEN `agent_com_amount` ELSE NULL END ) AS total_earning')->whereNotIn('status',static::$notAcceptedStatus)->where("customer_id", $user_id)->first();
        $total_booking = parent::whereNotIn('status',static::$notAcceptedStatus)->where("customer_id", $user_id)->count('id');
        $res[] = [
            'title'  => __("Pending"),
            'amount' => format_money_main($total_money->total_price - $total_money->total_earning),
            'desc'   => __("Total pending"),
            'class'  => 'purple',
            'icon'   => 'icon ion-ios-cart'
        ];
        $res[] = [
            'title'  => __("Earnings"),
            'amount' => format_money_main($total_money->total_earning ?? 0),
            'desc'   => __("Total earnings"),
            'class'  => 'info',
            'icon'   => 'icon ion-ios-gift'
        ];
        $res[] = [
            'title'  => __("Bookings"),
            'amount' => $total_booking,
            'desc'   => __("Total bookings"),
            'class'  => 'pink',
            'icon'   => 'icon ion-ios-pricetags'
        ];
        return $res;
    }

    public static function getEarningChartDataForAgent($from, $to, $user_id)
    {
        $data = [
            'labels'   => [],
            'datasets' => [
                [
                    'label'           => __("Total Earning"),
                    'data'            => [],
                    'backgroundColor' => '#F06292'
                ],
                [
                    'label'           => __("Total Pending"),
                    'data'            => [],
                    'backgroundColor' => '#8892d6'
                ]
            ]
        ];
        $sql_raw[] = 'sum( `agent_com_amount`) AS total_price';
        $sql_raw[] = 'sum( CASE WHEN `status` = "completed" THEN `agent_com_amount` ELSE NULL END ) AS total_earning';
        if (($to - $from) / DAY_IN_SECONDS > 90) {
            $year = date("Y", $from);
            // Report By Month
            for ($month = 1; $month <= 12; $month++) {
                $day_last_month = date("t", strtotime($year . "-" . $month . "-01"));
                $data['labels'][] = date("F", strtotime($year . "-" . $month . "-01"));
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("customer_id", $user_id)->whereBetween('created_at', [
                    $year . '-' . $month . '-01 00:00:00',
                    $year . '-' . $month . '-' . $day_last_month . ' 23:59:59'
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        } elseif (($to - $from) <= DAY_IN_SECONDS) {
            // Report By Hours
            for ($i = strtotime(date('Y-m-d', $from)); $i <= strtotime(date('Y-m-d 23:59:59', $to)); $i += HOUR_IN_SECONDS) {
                $data['labels'][] = date('H:i', $i);
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("customer_id", $user_id)->whereBetween('created_at', [
                    date('Y-m-d H:i:s', $i),
                    date('Y-m-d H:i:s', $i + HOUR_IN_SECONDS - 1),
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        } else {
            // Report By Day
            for ($i = strtotime(date('Y-m-d', $from)); $i <= strtotime(date('Y-m-d 23:59:59', $to)); $i += DAY_IN_SECONDS) {
                $data['labels'][] = display_date($i);
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("customer_id", $user_id)->whereBetween('created_at', [
                    date('Y-m-d 00:00:00', $i),
                    date('Y-m-d 23:59:59', $i),
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        }
        return $data;
    }

    public static function getTopCardsReportForVendor($user_id)
    {

        $res = [];
        $total_money = parent::selectRaw('sum( `total_before_fees` - `commission` - `agent_com_amount` + `vendor_service_fee_amount` ) AS total_price , sum( CASE WHEN `status` = "completed" THEN `total_before_fees` - `commission`- `agent_com_amount` + `vendor_service_fee_amount` ELSE NULL END ) AS total_earning')->whereNotIn('status',static::$notAcceptedStatus)->where("vendor_id", $user_id)->first();
        $total_booking = parent::whereNotIn('status',static::$notAcceptedStatus)->where("vendor_id", $user_id)->count('id');
        $total_service = 0;
        $services = get_bookable_services();
        if(!empty($services))
        {
            foreach ($services as $service){
                $total_service += $service::where('status', 'publish')->where("create_user", $user_id)->count('id');
            }
        }
        $res[] = [
            'title'  => __("Pending"),
            'amount' => format_money_main($total_money->total_price - $total_money->total_earning),
            'desc'   => __("Total pending"),
            'class'  => 'purple',
            'icon'   => 'icon ion-ios-cart'
        ];
        $res[] = [
            'title'  => __("Earnings"),
            'amount' => format_money_main($total_money->total_earning ?? 0),
            'desc'   => __("Total earnings"),
            'class'  => 'info',
            'icon'   => 'icon ion-ios-gift'
        ];
        $res[] = [
            'title'  => __("Bookings"),
            'amount' => $total_booking,
            'desc'   => __("Total bookings"),
            'class'  => 'pink',
            'icon'   => 'icon ion-ios-pricetags'
        ];
        $res[] = [
            'title'  => __("Services"),
            'amount' => $total_service,
            'desc'   => __("Total bookable services"),
            'class'  => 'success',
            'icon'   => 'icon ion-ios-flash'
        ];
        return $res;
    }

    public static function getEarningChartDataForVendor($from, $to, $user_id)
    {
        $data = [
            'labels'   => [],
            'datasets' => [
                [
                    'label'           => __("Total Earning"),
                    'data'            => [],
                    'backgroundColor' => '#F06292'
                ],
                [
                    'label'           => __("Total Pending"),
                    'data'            => [],
                    'backgroundColor' => '#8892d6'
                ]
            ]
        ];
        $sql_raw[] = 'sum( `total_before_fees` - `commission`- `agent_com_amount` + `vendor_service_fee_amount`) AS total_price';
        $sql_raw[] = 'sum( CASE WHEN `status` = "completed" THEN `total_before_fees` - `commission` - `agent_com_amount`+ `vendor_service_fee_amount` ELSE NULL END ) AS total_earning';
        if (($to - $from) / DAY_IN_SECONDS > 90) {
            $year = date("Y", $from);
            // Report By Month
            for ($month = 1; $month <= 12; $month++) {
                $day_last_month = date("t", strtotime($year . "-" . $month . "-01"));
                $data['labels'][] = date("F", strtotime($year . "-" . $month . "-01"));
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("vendor_id", $user_id)->whereBetween('created_at', [
                    $year . '-' . $month . '-01 00:00:00',
                    $year . '-' . $month . '-' . $day_last_month . ' 23:59:59'
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        } elseif (($to - $from) <= DAY_IN_SECONDS) {
            // Report By Hours
            for ($i = strtotime(date('Y-m-d', $from)); $i <= strtotime(date('Y-m-d 23:59:59', $to)); $i += HOUR_IN_SECONDS) {
                $data['labels'][] = date('H:i', $i);
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("vendor_id", $user_id)->whereBetween('created_at', [
                    date('Y-m-d H:i:s', $i),
                    date('Y-m-d H:i:s', $i + HOUR_IN_SECONDS - 1),
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        } else {
            // Report By Day
            for ($i = strtotime(date('Y-m-d', $from)); $i <= strtotime(date('Y-m-d 23:59:59', $to)); $i += DAY_IN_SECONDS) {
                $data['labels'][] = display_date($i);
                $dataBooking = parent::selectRaw(implode(",", $sql_raw))->where("vendor_id", $user_id)->whereBetween('created_at', [
                    date('Y-m-d 00:00:00', $i),
                    date('Y-m-d 23:59:59', $i),
                ])->whereNotIn('status',static::$notAcceptedStatus);
                $dataBooking = $dataBooking->first();
                $data['datasets'][1]['data'][] = $dataBooking->total_price - $dataBooking->total_earning;
                $data['datasets'][0]['data'][] = $dataBooking->total_earning ?? 0;
            }
        }
        return $data;
    }

}
