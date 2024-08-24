<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;
use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Session;
use Str;
use Illuminate\Support\Facades\Http;

class XenditController extends Controller
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
        $allowed_currency = array('IDR', 'PHP', 'USD', 'SGD', 'MYR');
        if (!in_array($currencyInfo->base_currency_text, $allowed_currency)) {
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
            'paymentMethod' => 'Xendit',
            'gatewayType' => 'online',
            'paymentStatus' => 'completed',
            'orderStatus' => 'pending'
        );

        /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~ Payment Gateway Info ~~~~~~~~~~~~~~
        ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
        $external_id = Str::random(10);
        $secret_key = 'Basic ' . config('xendit.key_auth');

        $data_request = Http::withHeaders([
            'Authorization' => $secret_key
        ])->post('https://api.xendit.co/v2/invoices', [
            'external_id' => $external_id,
            'amount' => $calculatedData['grandTotal'],
            'currency' => $currencyInfo->base_currency_text,
            'success_redirect_url' => route('shop.purchase_product.xendit.notify')
        ]);
        $response = $data_request->object();
        $response = json_decode(json_encode($response), true);
        if (!empty($response['success_redirect_url'])) {
            // put some data in session before redirect to paytm url
            $request->session()->put('paymentFor', $paymentFor);
            $request->session()->put('arrData', $arrData);
            $request->session()->put('xendit_id', $response['id']);
            $request->session()->put('secret_key', config('xendit.key_auth'));
            $request->session()->put('xendit_payment_type', 'shop');

            return redirect($response['invoice_url']);
        } else {
            return redirect()->route('check-out')->with(['alert-type' => 'error', 'message' => $response['message']]);
        }
    }

    public function callback($request)
    {
        $productList = $request->session()->get('productCart');
        $arrData = $request->session()->get('arrData');
        $data = $request->all();
        if ($data['status'] == 'PAID') {
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

    // return to success page
    public function notify(Request $request)
    {
        $productList = $request->session()->get('productCart');
        $arrData = $request->session()->get('arrData');
        $xendit_id = Session::get('xendit_id');
        $secret_key = Session::get('secret_key');
        if (!is_null($xendit_id) && $secret_key == config('xendit.key_auth')) {
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
