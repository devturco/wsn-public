<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\Shop\Product;
use Basel\MyFatoorah\MyFatoorah;
use Exception;
use Illuminate\Http\Request;
use Session;

class MyfatoorahController extends Controller
{
    public $myfatoorah;

    public function __construct()
    {
        $this->myfatoorah = MyFatoorah::getInstance(true);
    }

    public function index(Request $request, $paymentFor)
    {
        try {
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
            $allowed_currency = array('KWD', 'SAR', 'BHD', 'AED', 'QAR', 'OMR', 'JOD');
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
                'paymentMethod' => 'Myfatoorah',
                'gatewayType' => 'online',
                'paymentStatus' => 'completed',
                'orderStatus' => 'pending'
            );

            /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
            ~~~~~~~~~~~~~~~~~ Payment Gateway Info ~~~~~~~~~~~~~~
            ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
            $info = OnlineGateway::where('keyword', 'myfatoorah')->first();
            $information = json_decode(
                $info->information,
                true
            );
            $random_1 = rand(999, 9999);
            $random_2 = rand(9999, 99999);
            $result = $this->myfatoorah->sendPayment(
                $request['billing_name'],
                $calculatedData['grandTotal'],
                [
                    'CustomerMobile' => $information['sandbox_status'] == 1 ? '56562123544' : $request->phone,
                    'CustomerReference' => "$random_1",  //orderID
                    'UserDefinedField' => "$random_2", //clientID
                    "InvoiceItems" => [
                        [
                            "ItemName" => "Product Purchase",
                            "Quantity" => 1,
                            "UnitPrice" => $calculatedData['grandTotal']
                        ]
                    ]
                ]
            );
            if ($result && $result['IsSuccess'] == true) {
                $request->session()->put('myfatoorah_payment_type', 'shop');
                $request->session()->put('paymentFor', $paymentFor);
                $request->session()->put('arrData', $arrData);
                return redirect($result['Data']['InvoiceURL']);
            }
        } catch (Exception $e) {
            return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment Cancel']);
        }
    }

    public function successCallback(Request $request)
    {
        $productList = $request->session()->get('productCart');
        $arrData = $request->session()->get('arrData');
        if (!empty($request->paymentId)) {
            $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);
            if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Paid") {
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
                return [
                    'status' => 'success'
                ];
            } else {
                return [
                    'status' => 'fail'
                ];
            }
        } else {
            return [
                'status' => 'fail'
            ];
        }
    }

    public function failCallback(Request $request)
    {
        if (!empty($request->paymentId)) {
            $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);

            if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Pending") {
                return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment Cancel']);
            }
        }
    }
}
