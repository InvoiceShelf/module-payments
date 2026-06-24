<?php

namespace Modules\Payments\Services\Stripe;

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Services\PaymentInterface;
use Modules\Payments\Helpers\VersionHelper;

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
        $settings = PaymentMethod::getSettings(request()->payment_method_id);

        $this->settings = $settings["secret"];
    }

    /**
     * Get customer billing address (tries multiple relationship patterns)
     */
    private function getCustomerBilling($customer)
    {
        if (!$customer) {
            return null;
        }

        // Try different relationship patterns used by InvoiceShelf
        try {
            // Try addresses() relationship
            if (method_exists($customer, 'addresses')) {
                $address = $customer->addresses()->where('type', 'billing')->first();
                if ($address) {
                    return $address;
                }
                // Fallback to first address
                $address = $customer->addresses()->first();
                if ($address) {
                    return $address;
                }
            }
        } catch (\Exception $e) {
            // Continue to next attempt
        }

        try {
            // Try billingAddress() relationship
            if (method_exists($customer, 'billingAddress')) {
                return $customer->billingAddress;
            }
        } catch (\Exception $e) {
            // Continue to next attempt
        }

        try {
            // Try direct address property
            if (property_exists($customer, 'billing_address_street_1')) {
                // Address fields are directly on customer
                return $customer;
            }
        } catch (\Exception $e) {
            // Continue
        }

        return null;
    }

    /**
     * Get country code from customer billing
     */
    private function getCountryCode($customer)
    {
        $billing = $this->getCustomerBilling($customer);
        
        if (!$billing) {
            return null;
        }

        // Try to get country code from country_id
        $countryId = $billing->country_id ?? $billing->billing_country_id ?? null;
        
        if ($countryId) {
            try {
                $country = \App\Models\Country::find($countryId);
                if ($country) {
                    // Try various field names for 2-letter ISO code
                    $possibleFields = ['iso2', 'iso_2', 'code', 'short_code', 'country_code'];
                    
                    foreach ($possibleFields as $field) {
                        if (!empty($country->$field) && strlen($country->$field) === 2) {
                            return strtoupper($country->$field);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue if Country model lookup fails
            }
        }

        // Try direct country field
        $countryField = $billing->country ?? $billing->billing_country ?? null;
        if ($countryField && strlen($countryField) === 2) {
            return strtoupper($countryField);
        }

        return null;
    }

    /**
     * Search for existing Stripe customer by email
     */
    private function findExistingStripeCustomer($email)
    {
        if (!$email) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                    'Accept' => 'application/json',
                ])
                ->withToken($this->settings)
                ->get("https://api.stripe.com/v1/customers?email=" . urlencode($email) . "&limit=1");

            if ($response->status() === 200) {
                $data = $response->json();
                if (!empty($data['data']) && count($data['data']) > 0) {
                    return $data['data'][0]['id'];
                }
            }
        } catch (\Exception $e) {
            // If search fails, we'll create new customer
        }

        return null;
    }

    /**
     * Create or retrieve Stripe Customer
     */
    private function getOrCreateStripeCustomer($customer, $invoice)
    {
        if (!$customer || !$customer->email) {
            return null;
        }

        // IMPORTANT: Search for existing customer first to prevent duplicates
        $existingCustomerId = $this->findExistingStripeCustomer($customer->email);
        if ($existingCustomerId) {
            return $existingCustomerId;
        }

        // Build customer parameters
        $customerParams = [
            'email' => $customer->email,
        ];

        // Set name (both individual and business name for Stripe)
        if ($customer->name) {
            $customerParams['name'] = $customer->name;
        }

        if ($customer->phone) {
            $customerParams['phone'] = $customer->phone;
        }

        // Get customer's company name from custom field
        $customerCompanyName = $this->getCustomerCompanyName($customer);
        if ($customerCompanyName) {
            $customerParams['description'] = $customerCompanyName;
            // Also set as metadata
            $customerParams['metadata[company_name]'] = $customerCompanyName;
        }

        // Add address if available
        $billing = $this->getCustomerBilling($customer);
        if ($billing) {
            $address = [];
            
            // Try different field naming patterns
            $street1 = $billing->address_street_1 ?? $billing->billing_address_street_1 ?? 
                       $billing->street_1 ?? $billing->address_1 ?? null;
            $street2 = $billing->address_street_2 ?? $billing->billing_address_street_2 ?? 
                       $billing->street_2 ?? $billing->address_2 ?? null;
            $city = $billing->city ?? $billing->billing_city ?? null;
            $state = $billing->state ?? $billing->billing_state ?? null;
            $zip = $billing->zip ?? $billing->postal_code ?? $billing->billing_zip ?? null;
            
            if ($street1) {
                $address['line1'] = $street1;
            }
            if ($street2) {
                $address['line2'] = $street2;
            }
            if ($city) {
                $address['city'] = $city;
            }
            if ($state) {
                $address['state'] = $state;
            }
            if ($zip) {
                $address['postal_code'] = $zip;
            }
            
            // Get country code
            $countryCode = $this->getCountryCode($customer);
            if ($countryCode) {
                $address['country'] = $countryCode;
            }

            if (!empty($address)) {
                foreach ($address as $key => $value) {
                    $customerParams["address[{$key}]"] = $value;
                }
            }
        }

        // Add InvoiceShelf metadata
        $customerParams['metadata[invoiceshelf_customer_id]'] = $customer->id;
        $customerParams['metadata[source]'] = 'InvoiceShelf';

        // Build request body
        $bodyParts = [];
        foreach ($customerParams as $key => $value) {
            $bodyParts[] = urlencode($key) . '=' . urlencode($value);
        }
        $requestBody = implode('&', $bodyParts);

        // Create Stripe customer
        $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
            ->withToken($this->settings)
            ->withBody($requestBody, 'application/x-www-form-urlencoded')
            ->post("https://api.stripe.com/v1/customers");

        if ($response->status() === 200) {
            return $response->json()['id'];
        }

        return null;
    }

    /**
     * Get customer's company name from custom field
     */
    private function getCustomerCompanyName($customer)
    {
        if (!$customer) {
            return null;
        }

        try {
            // Try to get custom field value
            $customField = $customer->fields()
                ->where('model_type', 'Customer')
                ->whereHas('customField', function($query) {
                    $query->where('slug', 'CUSTOM_CUSTOMER_COMPANY_NAME')
                          ->orWhere('slug', 'custom_customer_company_name');
                })
                ->first();
            
            // InvoiceShelf stores custom field values in different columns based on type
            // For Input type fields, it uses string_answer
            if ($customField) {
                if (!empty($customField->string_answer)) {
                    return $customField->string_answer;
                } elseif (!empty($customField->value)) {
                    return $customField->value;
                }
            }
        } catch (\Exception $e) {
            // If custom fields aren't available, gracefully continue
        }

        return null;
    }

    public function generatePayment(Company $company, $invoice)
    {
        $currency = Currency::find($invoice->currency_id);
        $total = $invoice->total;

        // Get customer data from invoice
        $customer = $invoice->user ?? $invoice->customer ?? null;
        
        // Build the payment intent parameters
        $paymentParams = [
            'amount' => $total,
            'currency' => $currency->code,
        ];

        // Add enhanced description with invoice number and customer email
        if ($customer && $customer->email) {
            $paymentParams['description'] = "Payment on Invoice #{$invoice->invoice_number} - {$customer->email}";
        } else {
            $paymentParams['description'] = "Payment on Invoice #{$invoice->invoice_number}";
        }

        // Create or get Stripe customer and attach to payment intent
        $stripeCustomerId = $this->getOrCreateStripeCustomer($customer, $invoice);
        if ($stripeCustomerId) {
            $paymentParams['customer'] = $stripeCustomerId;
            
            // Set receipt_email for automatic receipts
            if ($customer->email) {
                $paymentParams['receipt_email'] = $customer->email;
            }
        }

        // Get customer's company name from custom field
        $customerCompanyName = $this->getCustomerCompanyName($customer);

        // Add comprehensive metadata
        $metadata = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'service_provider_id' => $company->id,
            'service_provider_name' => $company->name,
        ];

        if ($customer) {
            if ($customer->name) {
                $metadata['customer_name'] = $customer->name;
            }
            if ($customer->email) {
                $metadata['customer_email'] = $customer->email;
            }
            if ($customer->phone) {
                $metadata['customer_phone'] = $customer->phone;
            }
            if ($customer->id) {
                $metadata['customer_id'] = $customer->id;
            }
            // Add customer's company name if available
            if ($customerCompanyName) {
                $metadata['customer_company_name'] = $customerCompanyName;
            }
        }

        // Add metadata to payment params
        foreach ($metadata as $key => $value) {
            $paymentParams["metadata[{$key}]"] = $value;
        }

        // Build the request body with proper URL encoding
        // IMPORTANT: Convert boolean values to strings for Stripe API
        $bodyParts = [];
        foreach ($paymentParams as $key => $value) {
            if (is_bool($value)) {
                // Convert boolean to string "true" or "false"
                $bodyParts[] = urlencode($key) . '=' . ($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                // Handle nested arrays
                foreach ($value as $subKey => $subValue) {
                    if (is_bool($subValue)) {
                        $bodyParts[] = urlencode("{$key}[{$subKey}]") . '=' . ($subValue ? 'true' : 'false');
                    } else {
                        $bodyParts[] = urlencode("{$key}[{$subKey}]") . '=' . urlencode($subValue);
                    }
                }
            } else {
                $bodyParts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        $requestBody = implode('&', $bodyParts);

        // Create payment intent with enhanced data
        $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US'
            ])
            ->withToken($this->settings)
            ->withBody($requestBody, 'application/x-www-form-urlencoded')
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
