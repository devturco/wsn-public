<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Session;

class PerfectMoneyController extends Controller
{
    /*
     * Perfect Money Gateway
     */
    public static function index(Request $request, $event_id)
    {
        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~ Purchase Info ~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
        if ($request->session()->has('productCart')) {
            $productList = $request->session()->get('productCart');
        } else {
            Session::flash('error', 'Something went wrong!');

            return redirect()->route('shop.checkout')->withInput();
        }

        $purchaseProcess = new PurchaseProcessController();

        // do calculation
        $calculatedData = $purchaseProcess->calculation($request, $productList);

        $currencyInfo = Basic::first();

        $arrData = array(
            'billing_name' => $request['billing_name'],
            'billing_email' => $request['billing_email'],
            'billing_phone' => $request['billing_phone'],
            'billing_city' => $request['billing_city'],
            'billing_state' => $request['billing_state'],
            'billing_country' => $request['billing_country'],
            'billing_address' => $request['billing_address'],

            'shipping_name' => $request->checkbox == 1 ? $request['shipping_name'] : $request['billing_name'],

            'shipping_email' => $request->checkbox == 1 ? $request['shipping_email'] : $request['billing_email'],

            'shipping_phone' => $request->checkbox == 1 ? $request['shipping_phone'] : $request['billing_phone'],

            'shipping_city' => $request->checkbox == 1 ? $request['shipping_city'] : $request['billing_city'],

            'shipping_state' => $request->checkbox == 1 ? $request['shipping_state'] : $request['billing_state'],

            'shipping_country' => $request->checkbox == 1 ? $request['shipping_country'] : $request['billing_country'],

            'shipping_address' => $request->checkbox == 1 ? $request['shipping_address'] : $request['billing_address'],

            'total' => $calculatedData['total'],
            'discount' => $calculatedData['discount'],
            'productShippingChargeId' => $request->exists('shipping_method') ? $request['shipping_method'] : null,
            'shippingCharge' => $calculatedData['shippingCharge'],
            'tax' => $calculatedData['tax'],
            'grandTotal' => $calculatedData['grandTotal'],
            'currencyText' => $currencyInfo->base_currency_text,
            'currencyTextPosition' => $currencyInfo->base_currency_text_position,
            'currencySymbol' => $currencyInfo->base_currency_symbol,
            'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
            'paymentMethod' => 'Perfect Money',
            'gatewayType' => 'online',
            'paymentStatus' => 'completed',
            'orderStatus' => 'pending'
        );

        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~ Payment Gateway Info ~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~ Payment Gateway Init Start ~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

        $randomNo = substr(uniqid(), 0, 8);
        $websiteInfo = Basic::select('website_title')->first();
        $perfect_money = OnlineGateway::where('keyword', 'perfect_money')->first();
        $info = json_decode($perfect_money->information, true);
        $val['PAYEE_ACCOUNT'] = $info['perfect_money_wallet_id'];;
        $val['PAYEE_NAME'] = $websiteInfo->website_title;
        $val['PAYMENT_ID'] = "$randomNo"; //random id
        $val['PAYMENT_AMOUNT'] = $calculatedData['grandTotal'];
        // $val['PAYMENT_AMOUNT'] = 0.01; //test amount
        $val['PAYMENT_UNITS'] = "$currencyInfo->base_currency_text";

        $val['STATUS_URL'] = route('shop.purchase_product.perfect_money.notify');
        $val['PAYMENT_URL'] = route('shop.purchase_product.perfect_money.notify');
        $val['PAYMENT_URL_METHOD'] = 'GET';
        $val['NOPAYMENT_URL'] = route('shop.purchase_product.cancel');
        $val['NOPAYMENT_URL_METHOD'] = 'GET';
        $val['SUGGESTED_MEMO'] = $request['billing_name'];
        $val['BAGGAGE_FIELDS'] = 'IDENT';

        $data['val'] = $val;
        $data['method'] = 'post';
        $data['url'] = 'https://perfectmoney.com/api/step1.asp';
        $request->session()->put('payment_id', $randomNo);
        $request->session()->put('arrData', $arrData);

        return view('frontend.payment.perfect-money', compact('data'));
        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~ Payment Gateway Init End ~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
    }
    public function notify(Request $request)
    {
        // get the information from session
        $productList = $request->session()->get('productCart');
        $arrData = $request->session()->get('arrData');

        $perfect_money = OnlineGateway::where('keyword', 'perfect_money')->first();
        $perfectMoneyInfo = json_decode($perfect_money->information, true);
        $currencyInfo = Basic::select('base_currency_text')->first();

        $amo = $request['PAYMENT_AMOUNT'];
        $unit = $request['PAYMENT_UNITS'];
        $track = $request['PAYMENT_ID'];
        $id = Session::get('payment_id');
        $final_amount = $arrData['grandTotal'];
        // $final_amount = 0.01; //testing  amount

        if ($request->PAYEE_ACCOUNT == $perfectMoneyInfo['perfect_money_wallet_id'] && $unit == $currencyInfo->base_currency_text && $track == $id && $amo == round($final_amount, 2)) {
            //success
            $purchaseProcess = new PurchaseProcessController();

            // store product order information in database
            $orderInfo = $purchaseProcess->storeData($productList, $arrData);

            // then subtract each product quantity from respective product stock
            foreach ($productList as $key => $item) {
                $product = Product::query()->find($key);

                if ($product->product_type == 'physical') {
                    $stock = $product->stock - intval($item['quantity']);
                    $product->update(['stock' => $stock]);
                }
            }

            // generate an invoice in pdf format
            $invoice = $purchaseProcess->generateInvoice($orderInfo, $productList);

            // then, update the invoice field info in database
            $orderInfo->update(['invoice' => $invoice]);

            // send a mail to the customer with the invoice
            $purchaseProcess->prepareMail($orderInfo);

            // remove all session data
            $request->session()->forget('productCart');
            $request->session()->forget('discount');
            $request->session()->forget('paymentFor');
            $request->session()->forget('arrData');

            return redirect()->route('shop.purchase_product.complete');
        } else {
            return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment Canceled.']);
        }
    }
}
