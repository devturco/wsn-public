<?php

namespace App\Jobs;

use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;
use App\Models\Shop\Product;
use App\Models\Shop\ProductOrder;
use App\Models\Shop\ProductPurchaseItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IyzicoPendingProductPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderInfo = ProductOrder::where('id', $this->order_id)->first();
        $orderInfo->payment_status = 'completed';
        $orderInfo->save();
        $productList = ProductPurchaseItem::where('product_order_id', $orderInfo->id)->get();
        $purchase = new PurchaseProcessController();
        // generate an invoice in pdf format
        $invoice = $purchase->generateInvoice($orderInfo, $productList);

        // then, update the invoice field info in database
        $orderInfo->update(['invoice' => $invoice]);
        // send a mail to the customer with the invoice
        $purchase->prepareMail($orderInfo);
    }
}
