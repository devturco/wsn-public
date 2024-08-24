<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Midtrans\Snap;
use App\Http\Controllers\Controller;
use Midtrans\Config as MidtransConfig;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class MidtransController extends Controller
{
    public function index(Request $request, $paymentFor, $userType)
    {

        if ($request->session()->has('productCart')) {
            $productList = $request->session()->get('productCart');
        } else {
            Session::flash('error', 'Something went wrong!');

            return redirect()->route('shop.products');
        }

        $currencyInfo = $this->getCurrencyInfo();

        if ($currencyInfo->base_currency_text !== 'IDR') {
            return redirect()->back()->with('error', 'Invalid currency for midtrans payment.')->withInput();
        }


        $data = OnlineGateway::whereKeyword('midtrans')->first();
        $data = json_decode($data->information, true);

        MidtransConfig::$serverKey = $data['server_key'];
        if ($data['midtrans_mode'] == 1) {
            MidtransConfig::$isProduction = false;
        } elseif ($data['midtrans_mode'] == 0) {
            MidtransConfig::$isProduction = true;
        }
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;

        $purchaseProcess = new PurchaseProcessController();

        // do calculation
        $calculatedData = $purchaseProcess->calculation($request, $productList);

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
            'paymentMethod' => 'Midtrans',
            'gatewayType' => 'online',
            'paymentStatus' => 'completed',
            'orderStatus' => 'pending'
        );

        $params = [
            'transaction_details' => [
                'order_id' => uniqid(),
                'gross_amount' => intval($arrData['grandTotal']) * 1000, // will be multiplied by 1000
            ],

            'customer_details' => [
                'email' => $request->checkbox == 1 ? $request['shipping_email'] : $request['billing_email'],
                'phone' => $request->checkbox == 1 ? $request['shipping_phone'] : $request['billing_phone'],
                'name' => $request->checkbox == 1 ? $request['shipping_name'] : $request['billing_name'],
            ],

        ];

        $snapToken = Snap::getSnapToken($params);

        $title = 'Purchase Product';


        session()->put('paymentFor', $paymentFor);
        session()->put('arrData', $arrData);
        session()->put('title', $title);
        session()->put('userType', $userType);

        return view('frontend.payment.shop-midtrans', compact('snapToken', 'data'));
    }


    public function creditCardNotify()
    {
        // get the information from session
        $productList = session()->get('productCart');

        $arrData = session()->get('arrData');

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
        session()->forget('paymentFor');
        session()->forget('arrData');

        // remove session data
        session()->forget('productCart');
        session()->forget('discount');
        session()->forget('userType');
        return redirect()->route('shop.purchase_product.complete');
    }
}
