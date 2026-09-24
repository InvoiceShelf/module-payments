<?php

namespace Modules\Payments\Listeners;

use App\Events\ModuleDisabledEvent;
use App\Models\PaymentMethod;
use Modules\Payments\Helpers\VersionHelper;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
}

class ModuleDisabledListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(ModuleDisabledEvent $event)
    {
        if ($event->module->name !== config('payments.name')) {
            return false;
        }

        PaymentMethod::where('type', PaymentMethod::TYPE_MODULE)->update(['active' => false]);
    }
}
