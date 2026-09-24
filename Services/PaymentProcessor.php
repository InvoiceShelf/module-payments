<?php

namespace Modules\Payments\Services;

use App\Models\PaymentMethod;
use Modules\Payments\Helpers\VersionHelper;
use Modules\Payments\Services\Paypal\PaymentProvider;
use Modules\Payments\Services\Razorpay\PaymentProvider as RazorpayPaymentProvider;
use Modules\Payments\Services\Stripe\PaymentProvider as StripePaymentProvider;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
}

class PaymentProcessor
{
    private $paymentProvider;

    /**
     * Set payment providers.
     */
    public function __construct()
    {
        $driver = PaymentMethod::find(request()->payment_method_id)->driver;

        switch ($driver) {
            case 'stripe':
                $this->paymentProvider = new StripePaymentProvider;

                break;

            case 'razorpay':
                $this->paymentProvider = new RazorpayPaymentProvider;

                break;

            case 'paypal':
                $this->paymentProvider = new PaymentProvider;

                break;
        }
    }

    public function generatePayment($company, $invoice)
    {
        return $this->paymentProvider->generatePayment($company, $invoice);
    }

    public function confirmTransaction($company, $transaction_id, $request)
    {
        return $this->paymentProvider->confirmTransaction($company, $transaction_id, $request);
    }
}
