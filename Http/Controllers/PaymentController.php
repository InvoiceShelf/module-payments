<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Modules\Payments\Helpers\VersionHelper;
use Modules\Payments\Services\PaymentProcessor;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Http\Controllers\Controller', 'App\Http\Controllers\Controller');
    VersionHelper::aliasClass('InvoiceShelf\Models\Company', 'App\Models\Company');
    VersionHelper::aliasClass('InvoiceShelf\Models\Invoice', 'App\Models\Invoice');
}

class PaymentController extends Controller
{
    public function generatePayment(Company $company, Invoice $invoice, PaymentProcessor $processor, Request $request)
    {
        if ($invoice->paid_status == Invoice::STATUS_PAID && $invoice->status == Invoice::STATUS_COMPLETED) {
            return respondJson('invoice_paid.', 'Invoice Paid.');
        }

        $response = $processor->generatePayment($company, $invoice);

        return $response;
    }

    public function confirmTransaction(Company $company, $transaction_id, PaymentProcessor $processor, Request $request)
    {
        return $processor->confirmTransaction($company, $transaction_id, $request);
    }
}
