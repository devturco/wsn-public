<?php

use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('online_gateways', function (Blueprint $table) {
            $midtrans = OnlineGateway::where('keyword', 'midtrans')->first();
            //midtrans information
            if (empty($midtrans)) {
                $information = [
                    'server_key' => null,
                    'midtrans_mode' => null
                ];
                $data = [
                    'name' => 'Midtrans',
                    'keyword' => 'midtrans',
                    'information' => json_encode($information, true),
                    'status' => 0
                ];
                OnlineGateway::create($data);
            }

            //iyzico information
            $iyzico = OnlineGateway::where('keyword', 'iyzico')->first();
            if (empty($iyzico)) {
                $iyzico_information = [
                    'api_key' => null,
                    'secrect_key' => null,
                    'iyzico_mode' => null
                ];
                $iyzico_data = [
                    'name' => 'Iyzico',
                    'keyword' => 'iyzico',
                    'information' => json_encode($iyzico_information, true),
                    'status' => 0
                ];
                OnlineGateway::create($iyzico_data);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('online_gateways', function (Blueprint $table) {
            $midtrans = OnlineGateway::where('keyword', 'midtrans')->first();
            if ($midtrans) {
                $midtrans->delete();
            }
            $iyzico = OnlineGateway::where('keyword', 'iyzico')->first();
            if ($iyzico) {
                $iyzico->delete();
            }
        });
    }
};
