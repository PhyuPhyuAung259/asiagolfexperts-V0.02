<?php

namespace Themes\Golf\Booking\Controllers;

use App\Helpers\ReCaptchaEngine;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Mockery\Exception;
use Modules\Booking\Events\BookingCreatedEvent;
use Modules\Booking\Models\Booking;
use Modules\User\Events\SendMailUserRegistered;

class BookingController  extends \Modules\Booking\Controllers\BookingController
{

    public function doCheckout(Request $request)
    {
        /**
         * @var $booking Booking
         * @var $user User
         */
        $res = $this->validateDoCheckout();
        if($res !== true) return $res;
        $user = auth()->user();

        $booking = $this->bookingInst;

        if ( !in_array($booking->status , ['draft','unpaid'])) {
            return $this->sendError('',[
                'url'=>$booking->getDetailUrl()
            ]);
        }
        $service = $booking->service;
        if (empty($service)) {
            return $this->sendError(__("Service not found"));
        }

        $is_api = request()->segment(1) == 'api';

        /**
         * Google ReCapcha
         */
        if(!$is_api and ReCaptchaEngine::isEnable() and setting_item("booking_enable_recaptcha")){
            $codeCapcha = $request->input('g-recaptcha-response');
            if(!$codeCapcha or !ReCaptchaEngine::verify($codeCapcha)){
                return $this->sendError(__("Please verify the captcha"));
            }
        }

        $messages = [];
        $rules = [
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|string|email|max:255',
            'phone'           => 'required|string|max:255',
            'country' => 'required',
            'term_conditions' => 'required',
        ];


        $confirmRegister = $request->input('confirmRegister');
        if(!empty($confirmRegister)){
            $rules['password'] = 'required|string|confirmed|min:6|max:255';
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users')];
            $messages['password.confirmed']   =__('The password confirmation does not match');
            $messages['password.min']   = __('The password must be at least 6 characters');
        }

        $how_to_pay = $request->input('how_to_pay', '');
        $credit = $request->input('credit', 0);
        $payment_gateway = $request->input('payment_gateway');

        // require payment gateway except pay full
        if(empty(floatval($booking->deposit)) || $how_to_pay == 'deposit' || !auth()->check()){
            $rules['payment_gateway'] = 'required';
        }

        if(auth()->check()) {
            if ($credit > $user->balance) {
                return $this->sendError(__("Your credit balance is :amount", ['amount' => $user->balance]));
            }
        }else{
            // force credit to 0 if not login
            $credit = 0;
        }

        $rules = $service->filterCheckoutValidate($request, $rules);
        if (!empty($rules)) {

            $messages['term_conditions.required']    = __('Term conditions is required field');
            $messages['payment_gateway.required'] = __('Payment gateway is required field');

            $validator = Validator::make($request->all(), $rules , $messages );
            if ($validator->fails()) {
                return $this->sendError('', ['errors' => $validator->errors()]);
            }
        }

        $wallet_total_used = credit_to_money($credit);
        if($wallet_total_used > $booking->total){
            $credit = money_to_credit($booking->total,true);
            $wallet_total_used = $booking->total;
        }

        if($res = $service->beforeCheckout($request, $booking)){
            return $res;
        }

        if($how_to_pay=='full'and !empty($booking->deposit)){
            $booking->addMeta('old_deposit',$booking->deposit??0);
        }
        $oldDeposit = $booking->getMeta('old_deposit',0);
        if(empty(floatval($booking->deposit)) and !empty(floatval($oldDeposit))){
            $booking->deposit = $oldDeposit;
        }

        // Normal Checkout
        $booking->first_name = $request->input('first_name');
        $booking->last_name = $request->input('last_name');
        $booking->email = $request->input('email');
        $booking->phone = $request->input('phone');
        $booking->address = $request->input('address_line_1');
        $booking->address2 = $request->input('address_line_2');
        $booking->city = $request->input('city');
        $booking->state = $request->input('state');
        $booking->zip_code = $request->input('zip_code');
        $booking->country = $request->input('country');
        $booking->customer_notes = $request->input('customer_notes');
        $booking->gateway = $payment_gateway;
        $booking->wallet_credit_used = floatval($credit);
        $booking->wallet_total_used = floatval($wallet_total_used);
        $booking->pay_now = floatval((int)$booking->deposit == null ? $booking->total : (int)$booking->deposit);

        // If using credit
        if($booking->wallet_total_used > 0){
            if($how_to_pay == 'full'){
                $booking->deposit = 0;
                $booking->pay_now = $booking->total;
            }elseif($how_to_pay == 'deposit'){
                // case guest input credit more than "pay deposit" need to pay
                // Ex : pay deposit 10$ but guest input 20$ -> minus credit balance = 10$
                if($wallet_total_used > $booking->deposit){
                    $wallet_total_used = $booking->deposit;
                    $booking->wallet_total_used = floatval($wallet_total_used);
                    $booking->wallet_credit_used = money_to_credit($wallet_total_used,true);
                }

            }

            $booking->pay_now = max(0,$booking->pay_now - $wallet_total_used);
            $booking->paid = $booking->wallet_total_used;
        }else{
            if($how_to_pay == 'full'){
                $booking->deposit = 0;
                $booking->pay_now = $booking->total;
            }
        }

        $gateways = get_payment_gateways();
        if($booking->pay_now > 0){
            $gatewayObj = new $gateways[$payment_gateway]($payment_gateway);
            if (!empty($rules['payment_gateway'])) {
                if (empty($gateways[$payment_gateway]) or !class_exists($gateways[$payment_gateway])) {
                    return $this->sendError(__("Payment gateway not found"));
                }
                if (!$gatewayObj->isAvailable()) {
                    return $this->sendError(__("Payment gateway is not available"));
                }
            }
        }

        if($booking->wallet_credit_used && auth()->check()) {
            try {
                $transaction = $user->withdraw($booking->wallet_credit_used, [
                    'wallet_total_used' => $booking->wallet_total_used
                ]);

            }catch (\Exception $exception){
                return $this->sendError($exception->getMessage());
            }

            $booking->wallet_transaction_id = $transaction->id;
        }
        $booking->save();

//        event(new VendorLogPayment($booking));

        if(Auth::check()) {

        }elseif (!empty($confirmRegister)){
            $user = new User();
            $user->first_name = $request->input('first_name');
            $user->last_name = $request->input('last_name');
            $user->email = $request->input('email');
            $user->phone = $request->input('phone');
            $user->address = $request->input('address_line_1');
            $user->address2 = $request->input('address_line_2');
            $user->city = $request->input('city');
            $user->state = $request->input('state');
            $user->zip_code = $request->input('zip_code');
            $user->country = $request->input('country');
            $user->password = bcrypt($request->input('password'));
            $user->save();
            event(new Registered($user));
            Auth::loginUsingId($user->id);
            try {
                event(new SendMailUserRegistered($user));
            } catch (\Matrix\Exception $exception) {
                Log::warning("SendMailUserRegistered: " . $exception->getMessage());
            }
            $user->assignRole(setting_item('user_role'));
        }

        $booking->addMeta('locale',app()->getLocale());
        $booking->addMeta('how_to_pay',$how_to_pay);

        // Save Passenger
        $this->savePassengers($booking,$request);

        if($res = $service->afterCheckout($request, $booking)){
            return $res;
        }

        if($booking->pay_now > 0) {
            try {
                $gatewayObj->process($request, $booking, $service);
            } catch (Exception $exception) {
                return $this->sendError($exception->getMessage());
            }
        }else{
            if($booking->paid < $booking->total){
                $booking->status = $booking::PARTIAL_PAYMENT;
            }else{
                $booking->status = $booking::PAID;
            }

            if(!empty($booking->coupon_amount) and $booking->coupon_amount > 0 and $booking->total == 0){
                $booking->status = $booking::PAID;
            }

            $booking->save();
            event(new BookingCreatedEvent($booking));
            return $this->sendSuccess( [
                'url'=>$booking->getDetailUrl()
            ], __("You payment has been processed successfully"));
        }
    }
}
