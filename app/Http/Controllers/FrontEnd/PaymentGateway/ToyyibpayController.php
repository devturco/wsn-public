<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Session;

class ToyyibpayController extends Controller
{
    public function index(Request $request, $paymentFor)
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

        $currencyInfo = $this->getCurrencyInfo();
        if ($currencyInfo->base_currency_text != 'RM') {
            return back()->with(['alert-type' => 'error', 'message' => 'Invalid Currency.']);
        }

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
            'paymentMethod' => 'Toyyibpay',
            'gatewayType' => 'online',
            'paymentStatus' => 'completed',
            'orderStatus' => 'pending'
        );

        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~ Payment Gateway Info ~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
        $info = OnlineGateway::where('keyword', 'toyyibpay')->first();
        $information = json_decode($info->information, true);
        $ref = uniqid();
        session()->put('toyyibpay_ref_id', $ref);
        $bill_title = 'Product Purchase';
        $bill_description = 'Product Purchase via toyyibpay';

        $some_data = array(
            'userSecretKey' => $information['secret_key'],
            'categoryCode' => $information['category_code'],
            'billName' => $bill_title,
            'billDescription' => $bill_description,
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $calculatedData['grandTotal'] * 100,
            'billReturnUrl' => route('shop.purchase_product.toyyibpay.notify'),
            'billExternalReferenceNo' => $ref,
            'billTo' => $request['billing_name'],
            'billEmail' => $request['billing_email'],
            'billPhone' => $request['billing_phone'],
        );

        if ($information['sandbox_status'] == 1) {
            $host = 'https://dev.toyyibpay.com/'; // for development environment
        } else {
            $host = 'https://toyyibpay.com/'; // for production environment
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_URL, $host . 'index.php/api/createBill');  // sandbox will be dev.
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $some_data);

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $response = json_decode($result, true);
        if (!empty($response[0])) {
            // put some data in session before redirect to paytm url
            $request->session()->put('paymentFor', $paymentFor);
            $request->session()->put('arrData', $arrData);
            return redirect($host . $response[0]["BillCode"]);
        } else {
            return redirect()->route('shop.purchase_product.cancel');
        }

        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~ Payment Gateway Info End~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
    }
    public function notify(Request $request)
    {
        $productList = $request->session()->get('productCart');
        $arrData = $request->session()->get('arrData');
        $ref = session()->get('toyyibpay_ref_id');
        if ($request['status_id'] == 1 && $request['order_id'] == $ref) {
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
            return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment Cancel.']);
        }
    }
}
