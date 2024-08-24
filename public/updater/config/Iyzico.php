<?php

namespace Config;

use App\Models\PaymentGateway\OnlineGateway;

class Iyzipay
{
  public static function options()
  {
    $data = OnlineGateway::where('keyword', 'iyzico')->first();
    $information = json_decode($data->information, true);

    $options = new \Iyzipay\Options();
    $options->setApiKey($information['api_key']);
    $options->setSecretKey($information['secrect_key']);
    if ($information['iyzico_mode'] == 1) {
      $options->setBaseUrl("https://sandbox-api.iyzipay.com");
    } else {
      $options->setBaseUrl("https://api.iyzipay.com"); // production mode
    }
    return $options;
  }
}
