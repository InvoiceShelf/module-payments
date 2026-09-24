<?php

namespace Modules\Payments\Services\Razorpay;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Helpers\VersionHelper;
use Modules\Payments\Services\PaymentInterface;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\Company', 'App\Models\Company');
    VersionHelper::aliasClass('InvoiceShelf\Models\Currency', 'App\Models\Currency');
    VersionHelper::aliasClass('InvoiceShelf\Models\Payment', 'App\Models\Payment');
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
    VersionHelper::aliasClass('InvoiceShelf\Models\Transaction', 'App\Models\Transaction');
}

class PaymentProvider implements PaymentInterface
{
    private $settings;

    public function __construct()
    {
        $this->settings = PaymentMethod::getSettings(request()->payment_method_id);
    }

    public function generatePayment(Company $company, $invoice)
    {
        $currency = Currency::find($invoice->currency_id);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en_US',
        ])
            ->withBasicAuth($this->settings['key'], $this->settings['secret'])
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $invoice->total,
                'currency' => $currency->code,
            ]);

        if ($response->status() !== 200) {
            return $response->json();
        }

        $response = $response->json();

        $data = [
            'transaction_id' => $response['id'],
            'type' => 'razorpay',
            'status' => Transaction::PENDING,
            'transaction_date' => Carbon::now(),
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
        ];
        $transaction = Transaction::createTransaction($data);
        $response['transaction_unique_hash'] = $transaction->unique_hash;

        return [
            'order' => $response,
            'key' => $this->settings['key'],
            'currency' => $currency,
        ];
    }

    public function confirmTransaction(Company $company, $transaction_id, $request)
    {
        $transaction = Transaction::whereTransactionId($transaction_id)->first();

        $newHash = hash_hmac('sha256', "{$transaction_id}|{$request->payment_id}", $this->settings['secret']);

        if ($newHash == $request->signature) {
            $transaction->completeTransaction();

            $payment = Payment::generatePayment($transaction);

            return response()->json([
                'transaction' => $transaction,
                'payment' => $payment,
            ]);
        }

        $transaction->failedTransaction();

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function getOrder($transaction_id)
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])
            ->withBasicAuth($this->settings['key'], $this->settings['secret'])
            ->get("https://api.razorpay.com/v1/orders/{$transaction_id}")
            ->json();

        return $response;
    }
}
