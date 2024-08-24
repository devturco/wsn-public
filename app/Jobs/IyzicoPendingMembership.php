<?php

namespace App\Jobs;

use App\Models\Membership;
use Config\Iyzipay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IyzicoPendingMembership implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $memberhip_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($memberhip_id)
    {
        $this->memberhip_id = $memberhip_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $memberhip = Membership::where('id', $this->memberhip_id)->first();
        $conversion_id = $memberhip->conversation_id;

        $options = Iyzipay::options();
        $request = new \Iyzipay\Request\ReportingPaymentDetailRequest();
        $request->setPaymentConversationId($conversion_id);


        $paymentResponse = \Iyzipay\Model\ReportingPaymentDetail::create($request, $options);

        $result = (array) $paymentResponse;
        foreach ($result as $key => $data) {
            $data = json_decode($data, true);
            if ($data['status'] == 'success' && !is_null($data['payments'])) {
                if (is_array($data['payments'])) {
                    if ($data['payments'][0]['paymentStatus'] == 1) {
                        //success 
                        $memberhip->status = 1;
                        $memberhip->save();
                    }
                }
            }
        }
    }
}
