<?php

namespace App\Observers;

use App\Models\Customer;

class CustomerObserver
{
    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        if ($customer->isForceDeleting()) {
            $customer->vehicles()->forceDelete();
            $customer->checkins()->forceDelete();
        } else {
            $customer->vehicles()->delete();
            $customer->checkins()->delete();
        }
    }

    /**
     * Handle the Customer "restored" event.
     */
    public function restored(Customer $customer): void
    {
        $customer->vehicles()->restore();
        $customer->checkins()->restore();
    }
}
