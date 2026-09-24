<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Payments\Helpers\VersionHelper;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Http\Controllers\Controller', 'App\Http\Controllers\Controller');
    VersionHelper::aliasClass('InvoiceShelf\Models\Payment', 'App\Models\Payment');
    VersionHelper::aliasClass('InvoiceShelf\Models\Transaction', 'App\Models\Transaction');
}

class PaymentPdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Transaction $transaction, Request $request)
    {
        $payment = Payment::where('transaction_id', $transaction->id)->first();

        if (! $transaction->isExpired()) {
            return $payment->getGeneratedPDFOrStream('payment');
        }

        abort(403, 'Link Expired.');
    }
}
