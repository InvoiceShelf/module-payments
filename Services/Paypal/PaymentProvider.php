<?php

namespace Modules\Payments\Services\Paypal;

use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Helpers\VersionHelper;
use Modules\Payments\Services\PaymentInterface;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\Company', 'App\Models\Company');
    VersionHelper::aliasClass('InvoiceShelf\Models\Payment', 'App\Models\Payment');
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
    VersionHelper::aliasClass('InvoiceShelf\Models\Transaction', 'App\Models\Transaction');
}

class PaymentProvider implements PaymentInterface
{
    private $settings;

    private $auth_token;

    private $url;

    public function __construct()
    {
        $paymentProvider = PaymentMethod::find(request()->payment_method_id);
        $this->settings = $paymentProvider->settings;
        $this->url = 'https://api-m.paypal.com';

        if ($paymentProvider->use_test_env) {
            $this->url = 'https://api-m.sandbox.paypal.com';
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Accept-Language' => 'en_US',
        ])
            ->withBasicAuth($this->settings['key'], $this->settings['secret'])
            ->withBody('grant_type=client_credentials', 'application/x-www-form-urlencoded')
            ->post($this->url.'/v1/oauth2/token');

        if ($response->ok()) {
            $this->auth_token = $response->json()['access_token'];
        }
    }

    public function generatePayment(Company $company, $invoice) {}

    public function confirmTransaction(Company $company, $transaction_id, $request)
    {
        $data = [
            'transaction_id' => $transaction_id,
            'type' => 'paypal',
            'status' => Transaction::PENDING,
            'transaction_date' => Carbon::now(),
            'invoice_id' => $request->invoice_id,
            'company_id' => $request->header('company'),
        ];

        $transaction = Transaction::createTransaction($data);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->withToken($this->auth_token)
            ->get($this->url."/v2/checkout/orders/{$transaction->transaction_id}")
            ->json();

        if ($response['status'] !== 'COMPLETED') {
            $transaction->failedTransaction();

            return response()->json([
                'transaction' => $transaction,
            ]);
        }

        $transaction->completeTransaction();
        $payment = Payment::generatePayment($transaction);

        return response()->json([
            'transaction' => $transaction,
            'payment' => $payment,
        ]);
    }
}
