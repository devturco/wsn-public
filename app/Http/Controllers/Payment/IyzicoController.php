<?php

namespace App\Http\Controllers\Payment;

use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Package;
use App\Models\Language;
use App\Models\Membership;
use App\Models\VendorInfo;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;
use Cache;
use Config\Iyzipay;

class IyzicoController extends Controller
{
    public function paymentProcess(Request $request, $amount, $title, $cancel_url)
    {
        $requestData = $request->all();
        $cancel_url = $cancel_url;
        $notifyURL = route('membership.iyzico.notify');
        Session::put('request', $request->all());

        $currentLang = session()->has('lang') ?
            (Language::where('code', session()->get('lang'))->first())
            : (Language::where('is_default', 1)->first());
        $vendorinfo = VendorInfo::where('vendor_id', Auth::guard('vendor')->user()->id)->where('language_id', $currentLang->id)->with('vendor')->first();
        $vendor = Auth::guard('vendor')->user();
        $price  = round($request->price, 2);

        try {
            $options = Iyzipay::options();
            $conversion_id = uniqid(9999, 999999);
            $id_number = $request->identity_number;
            $basket_id = 'B'. uniqid(999, 99999);
            # create request class
            $request = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();
            $request->setLocale(\Iyzipay\Model\Locale::EN);
            $request->setConversationId($conversion_id);
            $request->setPrice("$price");
            $request->setPaidPrice("$price");
            $request->setCurrency(\Iyzipay\Model\Currency::TL);
            $request->setBasketId("$basket_id");
            $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
            $request->setCallbackUrl($notifyURL);
            $request->setEnabledInstallments(array(2, 3, 6, 9));

            $buyer = new \Iyzipay\Model\Buyer();
            $buyer->setId(uniqid());
            $buyer->setName(!empty($vendorinfo->name) ? $vendorinfo->name : $vendor->username);
            $buyer->setSurname(!empty($vendorinfo->name) ? $vendorinfo->name : $vendor->username);
            $buyer->setGsmNumber($vendor->phone);
            $buyer->setEmail($vendor->email);
            $buyer->setIdentityNumber("$id_number");
            $buyer->setLastLoginDate("");
            $buyer->setRegistrationDate("");
            $buyer->setRegistrationAddress($vendorinfo->address);
            $buyer->setIp("");
            $buyer->setCity($vendorinfo->city);
            $buyer->setCountry($vendorinfo->country);
            $buyer->setZipCode($vendorinfo->zip_code);
            $request->setBuyer($buyer);

            $shippingAddress = new \Iyzipay\Model\Address();
            $shippingAddress->setContactName(!empty($vendorinfo->name) ? $vendorinfo->name : $vendor->username);
            $shippingAddress->setCity($vendorinfo->city);
            $shippingAddress->setCountry($vendorinfo->country);
            $shippingAddress->setAddress($vendorinfo->address);
            $shippingAddress->setZipCode("$vendorinfo->zip_code");
            $request->setShippingAddress($shippingAddress);

            $billingAddress = new \Iyzipay\Model\Address();
            $billingAddress->setContactName(!empty($vendorinfo->name) ? $vendorinfo->name : $vendor->username);
            $billingAddress->setCity($vendorinfo->city);
            $billingAddress->setCountry($vendorinfo->country);
            $billingAddress->setAddress($vendorinfo->address);
            $billingAddress->setZipCode("$vendorinfo->zip_code");
            $request->setBillingAddress($billingAddress);

            $q_id = uniqid(999, 99999);
            $basketItems = array();
            $firstBasketItem = new \Iyzipay\Model\BasketItem();
            $firstBasketItem->setId($q_id);
            $firstBasketItem->setName("Purchase Id " . $q_id);
            $firstBasketItem->setCategory1("Purchase or Booking");
            $firstBasketItem->setCategory2("");
            $firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
            $firstBasketItem->setPrice($price);
            $basketItems[0] = $firstBasketItem;
            $request->setBasketItems($basketItems);

            # make request
            $payWithIyzicoInitialize = \Iyzipay\Model\PayWithIyzicoInitialize::create($request, $options);
        } catch (\Exception $th) {
        }

        $paymentResponse = (array)$payWithIyzicoInitialize;

        foreach ($paymentResponse as $key => $data) {
            $paymentInfo = json_decode($data, true);
            if ($paymentInfo['status'] == 'success') {
                if (!empty($paymentInfo['payWithIyzicoPageUrl'])) {
                    Cache::forget('conversation_id');
                    Session::put('iyzico_token', $paymentInfo['token']);
                    Session::put('conversation_id', $conversion_id);
                    Cache::put('conversation_id', $conversion_id, 60000);

                    //return for payment
                    return redirect($paymentInfo['payWithIyzicoPageUrl']);
                }
            }
            return redirect($cancel_url);
        }
    }

    public function notify(Request $request)
    {
        $requestData = Session::get('request');
        $requestData['status'] = 0;
        $requestData['conversation_id'] = Session::get('conversation_id');
        $bs = Basic::first();

        $paymentFor = Session::get('paymentFor');

        $package = Package::find($requestData['package_id']);
        $transaction_id = VendorPermissionHelper::uniqidReal(8);
        $transaction_details = "online";

        if ($paymentFor == "membership") {
            $amount = $requestData['price'];
            $password = $requestData['password'];
            $checkout = new VendorCheckoutController();

            $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

            $lastMemb = $vendor->memberships()->orderBy('id', 'DESC')->first();

            $activation = Carbon::parse($lastMemb->start_date);
            $expire = Carbon::parse($lastMemb->expire_date);
            $file_name = $this->makeInvoice($requestData, "membership", $vendor, $password, $amount, "Paypal", $requestData['phone'], $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

            $mailer = new MegaMailer();
            $data = [
                'toMail' => $vendor->email,
                'toName' => $vendor->fname,
                'username' => $vendor->username,
                'package_title' => $package->title,
                'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
                'discount' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->discount . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
                'total' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
                'activation_date' => $activation->toFormattedDateString(),
                'expire_date' => Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
                'membership_invoice' => $file_name,
                'website_title' => $bs->website_title,
                'templateType' => 'registration_with_premium_package',
                'type' => 'registrationWithPremiumPackage'
            ];
            $mailer->mailFromAdmin($data);
            @unlink(public_path('assets/front/invoices/' . $file_name));

            session()->flash('success', 'Your payment has been completed.');
            Session::forget('request');
            Session::forget('paymentFor');
            return redirect()->route('success.page');
        } elseif ($paymentFor == "extend") {
            $amount = $requestData['price'];
            $password = uniqid('qrcode');
            $checkout = new VendorCheckoutController();
            $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

            $lastMemb = Membership::where('vendor_id', $vendor->id)->orderBy('id', 'DESC')->first();
            $activation = Carbon::parse($lastMemb->start_date);
            $expire = Carbon::parse($lastMemb->expire_date);

            $file_name = $this->makeInvoice($requestData, "extend", $vendor, $password, $amount, $requestData["payment_method"], $vendor->phone, $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

            $mailer = new MegaMailer();
            $data = [
                'toMail' => $vendor->email,
                'toName' => $vendor->fname,
                'username' => $vendor->username,
                'package_title' => $package->title,
                'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
                'activation_date' => $activation->toFormattedDateString(),
                'expire_date' => Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
                'membership_invoice' => $file_name,
                'website_title' => $bs->website_title,
                'templateType' => 'membership_extend',
                'type' => 'membershipExtend'
            ];
            $mailer->mailFromAdmin($data);
            @unlink(public_path('assets/front/invoices/' . $file_name));

            Session::forget('request');
            Session::forget('paymentFor');
            return redirect()->route('success.page');
        }
    }

    public function iyzicoCancle()
    {
        $requestData = Session::get('request');
        $paymentFor = Session::get('paymentFor');
        session()->flash('warning', __('cancel payment'));
        if ($paymentFor == "membership") {
            return redirect()->route('front.register.view', ['status' => $requestData['package_type'], 'id' => $requestData['package_id']])->withInput($requestData);
        } else {
            return redirect()->route('vendor.plan.extend.checkout', ['package_id' => $requestData['package_id']])->withInput($requestData);
        }
    }
}
