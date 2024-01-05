<?php

namespace Themes\Golf\Agent\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\FrontendController;
use Themes\Golf\Booking\Models\Booking;

class DashboardController extends FrontendController
{

    private Booking $booking;

    public function __construct(Booking $booking)
    {
        parent::__construct();
        $this->booking = $booking;
    }

    public function index(){
        $user_id = auth()->id();
        $data = [
            'cards_report'       => $this->booking->getTopCardsReportForAgent($user_id),
            'earning_chart_data' => $this->booking->getEarningChartDataForAgent(strtotime('monday this week'), time(), $user_id),
            'page_title'=>__("Dashboard")
        ];

        return view("Agent::frontend.agent.dashboard",$data);
    }


    public function reloadChart(Request $request)
    {
        $chart = $request->input('chart');
        $user_id = Auth::id();
        switch ($chart) {
            case "earning":
                $from = $request->input('from');
                $to = $request->input('to');
                return $this->sendSuccess([
                    'data' => $this->booking->getEarningChartDataForAgent(strtotime($from), strtotime($to), $user_id)
                ]);
                break;
        }
    }
}
