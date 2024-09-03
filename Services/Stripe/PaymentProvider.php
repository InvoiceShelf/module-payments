<?php

namespace Modules\Payments\Services\Stripe;

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\Currency;
use Crater\Models\Payment;
use Crater\Models\PaymentMethod;
use Crater\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Services\PaymentInterface;

class PaymentProvider implements PaymentInterface
{
    private $settings;

    public function __construct()
    {
        $settings = PaymentMethod::getSettings(request()->payment_method_id);

        $this->settings = $settings["secret"];
    }

    public function generatePayment(Company $company, $invoice)
    {
        $currency = Currency::find($invoice->currency_id);
        $total = $invoice->total;

        $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US'
            ])
            ->withToken($this->settings)
            ->withBody("amount={$total}&currency={$currency->code}", 'application/x-www-form-urlencoded')
            ->post("https://api.stripe.com/v1/payment_intents");

        if ($response->status() !== 200) {
            return $response->json();
        }

        $response = $response->json();

        $data = [
            'transaction_id' => $response['id'],
            'type' => 'stripe',
            'status' => Transaction::PENDING,
            'transaction_date' => Carbon::now(),
            'invoice_id' => $invoice->id,
            'company_id' => $company->id
        ];
        $transaction = Transaction::createTransaction($data);

        $response['transaction_unique_hash'] = $transaction->unique_hash;

        return [
            'order' => $response,
            'currency' => $currency
        ];
    }

    public function confirmTransaction(Company $company, $transaction_id, $request)
    {
        $response = $this->getOrder($transaction_id);

        $transaction = Transaction::whereTransactionId($transaction_id)->first();

        if ($response['amount_received'] == $response['amount'] && $response['status'] == 'succeeded') {
            $transaction->completeTransaction();

            $payment = Payment::generatePayment($transaction);

            return response()->json([
                'transaction' => $transaction,
                'payment' => $payment
            ]);
        }

        if ($response['status'] == 'payment_failed') {
            $transaction->failedTransaction();

            return response()->json([
                'transaction' => $transaction,
            ]);
        }

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function getOrder($transaction_id)
    {
        $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
            ->withToken($this->settings)
            ->get("https://api.stripe.com/v1/payment_intents/{$transaction_id}")
            ->json();

        return $response;
    }
}
