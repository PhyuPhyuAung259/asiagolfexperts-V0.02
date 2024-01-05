<?php
namespace Themes\Golf\Golf\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\Booking\Models\Booking;
use Modules\FrontendController;
use Themes\Golf\Golf\Models\Golf;
use Themes\Golf\Golf\Models\GolfDate;

class AvailabilityController extends FrontendController{

    protected $golfClass;

    protected $golfDateClass;

    /**
     * @var Booking
     */
    protected $bookingClass;

    protected $indexView = 'Golf::frontend.user.availability';

    public function __construct(Golf $golfClass, GolfDate $golfDateClass,Booking $bookingClass)
    {
        parent::__construct();
        $this->golfDateClass = $golfDateClass;
        $this->bookingClass = $bookingClass;
        $this->golfClass = $golfClass;
    }

    public function callAction($method, $parameters)
    {
        if(!Golf::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function index(Request $request){
        $this->checkPermission('golf_create');

        $q = $this->golfClass::query();

        if($request->query('s')){
            $q->where('title','like','%'.$request->query('s').'%');
        }

        if(!$this->hasPermission('golf_manage_others')){
            $q->where('author_id',$this->currentUser()->id);
        }

        $q->orderBy('bravo_golfs.id','desc');

        $rows = $q->paginate(15);

        $current_month = strtotime(date('Y-m-01',time()));

        if($request->query('month')){
            $date = date_create_from_format('m-Y',$request->query('month'));
            if(!$date){
                $current_month = time();
            }else{
                $current_month = $date->getTimestamp();
            }
        }
        $breadcrumbs = [
            [
                'name' => __('Golf Courses'),
                'url'  => route('golf.vendor.index')
            ],
            [
                'name'  => __('Availability'),
                'class' => 'active'
            ],
        ];
        $page_title = __('Golf Course Availability');

        return view($this->indexView,compact('rows','breadcrumbs','current_month','page_title','request'));
    }

    public function loadDates(Request $request){
        $rules = [
            'id'=>'required',
            'start'=>'required',
            'end'=>'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
        $golf = $this->golfClass::find($request->query('id'));
        if(empty($golf)){
            return $this->sendError(__('Golf not found'));
        }
        $lang = app()->getLocale();
        $is_single = $request->query('for_single');

        $query = GolfDate::query();
        $query->where('target_id',$request->query('id'));
        $query->where('start_date','>=',date('Y-m-d H:i:s',strtotime($request->query('start'))));
        $query->where('end_date','<=',date('Y-m-d H:i:s',strtotime($request->query('end'))));
        $rows =  $query->take(50)->get();
        $allDates = [];
        $period = periodDate($request->input('start'),$request->input('end'));
        foreach ($period as $dt){
            $date = [
                'id'=>rand(0,999),
                'active'=>0,
                'textColor'=>'#2791fe',
                'price'=>(!empty($golf->sale_price) and $golf->sale_price > 0 and $golf->sale_price < $golf->price) ? $golf->sale_price : $golf->price,
                'cart_price'=> $golf->cart_price??0,
                'cart_sharing_price'=> $golf->cart_sharing_price??0,
                'time_slot'=>[]
            ];
            $date['start'] = $date['end'] = $dt->format('Y-m-d');
            if($golf->default_state){
                $date['active'] = 1;
                $date['title'] = '';
            }else{
                $date['title'] = $date['event'] = __('Blocked');
                $date['backgroundColor'] = 'orange';
                $date['borderColor'] = '#fe2727';
                $date['classNames'] = ['blocked-event'];
                $date['textColor'] = '#fe2727';
            }
            if ($golf->ticket_types and $golf->getBookingType() == "ticket") {
                $date['ticket_types'] = $golf->ticket_types;
                $c_title = "";
                foreach ($date['ticket_types'] as &$ticket) {
                    $ticket['name'] = !empty($ticket['name_' . $lang])?$ticket['name_' . $lang]:$ticket['name'];
                    if (!$is_single) {
                        $c_title .= $ticket['name'] . ": " . format_money_main($ticket['price']) ." x ".$ticket['number']. "<br>";
                        //for single
                        $ticket['display_price'] = format_money_main($ticket['price']);
                    } else {
                        $c_title .= $ticket['name'] . ": " . format_money($ticket['price']) ." x ".$ticket['number']. "<br>";
                        //for single
                        $ticket['display_price'] = format_money($ticket['price']);
                    }
                    $ticket['min'] = 0;
                    $ticket['max'] = $ticket['number'];
                    if ($is_single) {
                        $ticket['number'] = 0;
                    }
                }
                $date['ticket_types'] = array_values($date['ticket_types']);
                $date['title'] = $date['event'] = $c_title;
            }
            if ($golf->getBookingType() == "time_slot") {
                if (!$is_single) {
                    $date['title'] = $date['event'] = format_money_main($date['price']);
                } else {
                    $date['title'] = $date['event'] = format_money($date['price']);
                }
                if ($time_slots = $golf->getBookingTimeSlot()) {
                    $date['booking_time_slots'] = $time_slots;
                }
            }
            $allDates[$dt->format('Y-m-d')] = $date;
        }
        if(!empty($rows))
        {
            foreach ($rows as $row)
            {
                $ticketData = $allDates[date('Y-m-d',strtotime($row->start_date))];
                if ($row->ticket_types and $golf->getBookingType() == "ticket") {
                    $list_ticket_types = $row->ticket_types;
                    $c_title = "";
                    foreach ( $list_ticket_types as $k=>&$ticket){
                        $ticket['name'] = !empty($ticket['name_' . $lang])?$ticket['name_' . $lang]:$ticket['name'];
                        if(!$is_single){
                            $c_title .= $ticket['name'].": ".format_money_main($ticket['price'])." x ".$ticket['number']."<br>";
                            //for single
                            $ticket['display_price'] = format_money_main($ticket['price']);
                        }else{
                            $c_title .= $ticket['name'].": ".format_money($ticket['price'])." x ".$ticket['number']."<br>";
                            //for single
                            $ticket['display_price'] = format_money($ticket['price']);
                        }
                        $ticket['min'] = 0;
                        $ticket['max'] = $ticket['number'];
                        if($is_single){
                            $ticket['number'] = 0;
                        }
                    }
                    $ticketData['title'] = $ticketData['event']  = $c_title;
                    $ticketData['ticket_types'] = $list_ticket_types;
                }

                if ($golf->getBookingType() == "time_slot") {
                    if (!$is_single) {
                        $ticketData['title'] = $ticketData['event'] = format_money_main($row['price']);
                    } else {
                        $ticketData['title'] = $ticketData['event'] = format_money($row['price']);
                    }
                    $ticketData['price'] = $row['price'];
                    $ticketData['cart_price'] = $row['cart_price'];
                    $ticketData['cart_sharing_price'] = $row['cart_sharing_price'];

                }
                if(!$row->active)
                {
                    $ticketData['title'] = $row->event = __('Blocked');
                    $ticketData['backgroundColor'] = '#fe2727';
                    $ticketData['classNames'] = ['blocked-event'];
                    $ticketData['textColor'] = '#fe2727';
                    $ticketData['active'] = 0;
                }else{
                    $ticketData['classNames'] = ['active-event'];
                    $ticketData['active'] = 1;
                    $ticketData['title'] = '';
                }
                $timeSlot = $row['time_slot']??[];
                $ticketData['time_slot'] = array_values($timeSlot);

                $allDates[date('Y-m-d',strtotime($row->start_date))] = $ticketData;
            }
        }
        $bookings = $this->bookingClass::getAllBookingInRanges($golf->id,$golf->type,$request->query('start'),$request->query('end'));
        if(!empty($bookings))
        {
            foreach ($bookings as $booking){
                $period = periodDate($booking->start_date,$booking->end_date);
                foreach ($period as $dt){
                    $date = $dt->format('Y-m-d');
                    if(isset($allDates[$date])){
                        $isBook = false;

                        if($golf->getBookingType() == "ticket")
                        {
                            $c_title = "";
                            $list_ticket_types = $allDates[$dt->format('Y-m-d')]['ticket_types'];
                            $bookingTicketTypes = $booking->getJsonMeta('ticket_types') ?? [];
                            foreach ($bookingTicketTypes as $bookingTicket){
                                $numberBoook = $bookingTicket['number'];
                                foreach ($list_ticket_types as &$ticket){
                                    if( $ticket['code'] == $bookingTicket['code']){
                                        $ticket['max'] =  $ticket['max'] - $numberBoook;
                                        if($ticket['max'] <= 0){
                                            $ticket['max'] = 0;
                                        }
                                        $c_title .= $ticket['name'].": ".format_money_main($ticket['price'])." x ".$ticket['max']."<br>";
                                    }
                                    if($ticket['max'] > 0){
                                        $isBook = true;
                                    }
                                }
                            }
                            $allDates[$dt->format('Y-m-d')]['title'] = $c_title;
                            $allDates[$dt->format('Y-m-d')]['ticket_types'] = $list_ticket_types;
                        }
                        if($golf->getBookingType() == "time_slot")
                        {
                            $timeSlots = $booking->time_slots;
                            $date_slots = collect($allDates[$date]['time_slot']);
                            foreach ($timeSlots as $item){
                                $value = date("H:i",strtotime($item->start_time));
                                $date_slots->reject(function ($slot) use($value) {
                                    return $slot['time']  === $value;
                                });
                            }
                            if(!count($date_slots)){
                                $isBook = true;
                            }
                            $allDates[$date]['time_slot'] = array_values($date_slots->all());
                        }

                        if($isBook == false){
                            $allDates[$date]['active'] = 0;
                            $allDates[$date]['event'] = __('Full Book');
                            $allDates[$date]['title'] = __('Full Book');
                            $allDates[$date]['classNames'] = ['full-book-event'];
                        }
                    }
                }
            }
        }
        $data = array_values($allDates);
        return response()->json($data);
    }

    public function store(Request $request){

        $request->validate([
            'target_id'=>'required',
            'start_date'=>'required',
            'end_date'=>'required'
        ]);
        $golf = $this->golfClass::find($request->input('target_id'));
        $target_id = $request->input('target_id');
        if(empty($golf)){
            return $this->sendError(__('Golf not found'));
        }
        if(!$this->hasPermission('golf_manage_others')){
            if($golf->author_id != Auth::id()){
                return $this->sendError("You do not have permission to access it");
            }
        }
        $postData = $request->input();
        $period = periodDate($request->input('start_date'),$request->input('end_date'));
        foreach ($period as $dt){

            $date = GolfDate::where('start_date',$dt->format('Y-m-d'))->where('target_id',$target_id)->first();
            if(empty($date)){
                $date = new GolfDate();
                $date->target_id = $target_id;
            }
            $postData['start_date'] = $dt->format('Y-m-d H:i:s');
            $postData['end_date'] = $dt->format('Y-m-d H:i:s');

            $date->fillByAttr([
                'start_date','end_date','active','price','cart_price','cart_sharing_price','time_slot'
            ],$postData);

            $date->save();
        }
        return $this->sendSuccess([],__("Update Success"));
    }

    public function storeBulkEdit(Request $request)
    {
        $rules = [
            'service_id'    => 'required',
            'start_date' => 'required',
            'end_date'   => 'required'
        ];
        $request->validate($rules);

        $serviceId = $request->input('service_id');
        $ticket_types = $request->input("ticket_types");

        $row = $this->golfClass::find($serviceId);

        if (empty($row)) {
            return $this->sendError(__('Golf not found'));
        }


        $dayOfWeek =$request->input('day_of_week_select')??[];
        $check_all = Arr::where($dayOfWeek,function($value,$item){
            return $value==8;
        });

        $postData = $request->input();
        $postData['price'] = $request->input('price',0);

        unset($postData['service_id']);
        unset($postData['day_of_week_select']);
        unset($postData['min_guests']);
        if(!empty($ticket_types)){
            unset($postData['price']);
        }else{
            unset($postData['ticket_types']);
        }
        try {
            for ($i = strtotime($request->input('start_date')); $i <= strtotime($request->input('end_date')); $i += DAY_IN_SECONDS) {
                $date_n = date('N',$i);
                $postData['start_date'] = date('Y-m-d 00:00:00', $i);
                $postData['end_date'] = date('Y-m-d 00:00:00', $i);
                $postData['active']  = $request->input('active');
                if(!empty($check_all)){
                    $this->_save($postData,[$serviceId],$i);
                }else{
                    if(in_array($date_n,$dayOfWeek)){
                        $this->_save($postData,[$serviceId],$i);
                    }
//                    else{
//                        $postData['active']  = !$postData['active'];
//                        $this->_save($postData,[$serviceId],$i);
//                    }
                }
            }
            return $this->sendSuccess([], __("Update Success"));
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }


    }

    private function _save($postData,$ids,$date){
        foreach ($ids as $id) {
            $postData['target_id']= $id;
            $postData['update_user']= Auth::id();
            $this->golfDateClass::where('target_id', $id)
                ->where('start_date', date('Y-m-d', $date))
                ->delete();
            [$attr] = Arr::divide($postData);
            $golfDate = new GolfDate();
            $golfDate->fillByAttr($attr,$postData)->save();
        }
    }

    public function loadDataService(Request $request){
        $row = $this->golfClass::find($request->input('id'));
        if(!empty($row)){
            return $this->sendSuccess(['price'=>(!empty($row->sale_price) and $row->sale_price > 0 and $row->sale_price < $row->price) ? $row->sale_price : $row->price,
                                       'cart_price'=> $row->cart_price??0,
                                       'cart_sharing_price'=> $row->cart_sharing_price??0,
                                       'time_slot'=>[]]);
        }else{
            return $this->sendError('Not found');
        }
    }
}
