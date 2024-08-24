<?php

namespace App\Http\Controllers;

use App\Http\Helpers\VendorPermissionHelper;
use App\Jobs\IyzicoPendingMembership;
use App\Jobs\IyzicoPendingProductPurchase;
use App\Jobs\SubscriptionExpiredMail;
use App\Jobs\SubscriptionReminderMail;
use App\Models\BasicSettings\Basic;
use App\Models\Membership;
use App\Models\Shop\ProductOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CronJobController extends Controller
{
    public function index()
    {
        try {
            $bs = Basic::first();

            $expired_members = Membership::whereDate('expire_date', Carbon::now()->subDays(1))->get();
            foreach ($expired_members as $key => $expired_member) {
                if (!empty($expired_member->vendor)) {
                    $vendor = $expired_member->vendor;
                    $current_package = VendorPermissionHelper::userPackage($vendor->id);
                    if (is_null($current_package)) {
                        SubscriptionExpiredMail::dispatch($vendor, $bs);
                    }
                }
            }

            $remind_members = Membership::whereDate('expire_date', Carbon::now()->addDays($bs->expiration_reminder))->get();
            foreach ($remind_members as $key => $remind_member) {
                if (!empty($remind_member->vendor)) {
                    $vendor = $remind_member->vendor;

                    $nextPacakgeCount = Membership::where([
                        ['vendor_id', $vendor->id],
                        ['start_date', '>', Carbon::now()->toDateString()]
                    ])->where('status', '<>', 2)->count();

                    if ($nextPacakgeCount == 0) {
                        SubscriptionReminderMail::dispatch($vendor, $bs, $remind_member->expire_date);
                    }
                }
                \Artisan::call("queue:work --stop-when-empty");
            }

            //get iyzico pending memberships
            $pending_meberships = Membership::where([['payment_method', 'Iyzico'], ['status', 0]])->get();
            foreach ($pending_meberships as $pending_mebership) {
                IyzicoPendingMembership::dispatch($pending_mebership->id);
            }

            //get iyzico pending orders
            $pending_orders = ProductOrder::where([['payment_method', 'Iyzico'], ['payment_status', 'pending']])->get();
            foreach ($pending_orders as $order) {
                IyzicoPendingProductPurchase::dispatch($order->id);
            }
        } catch (\Exception $e) {
        }
    }
}
