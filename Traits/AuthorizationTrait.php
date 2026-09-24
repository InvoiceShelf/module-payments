<?php

namespace Modules\Payments\Traits;

use Illuminate\Support\Facades\Http;

trait AuthorizationTrait
{
    public function checkAuthorization($driver, $key, $secret, $useTestEnv = false)
    {
        // paypal authorization
        if ($driver == 'paypal') {
            $url = 'https://api-m.paypal.com';

            if ($useTestEnv) {
                $url = 'https://api-m.sandbox.paypal.com';
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
            ])
                ->withBasicAuth($key, $secret)
                ->withBody('grant_type=client_credentials', 'application/x-www-form-urlencoded')
                ->post("{$url}/v1/oauth2/token");

            if ($response->getStatusCode() == 200) {
                return true;
            }

            return false;
        }

        // stripe authorization
        if ($driver == 'stripe') {
            $response = Http::withToken($secret)->get('https://api.stripe.com/v1/orders');

            if ($response->getStatusCode() == 200) {
                return true;
            }

            return false;
        }

        // razorpay authorization
        if ($driver == 'razorpay') {
            $response = Http::withBasicAuth($key, $secret)->get('https://api.razorpay.com/v1/orders');

            if ($response->getStatusCode() == 200) {
                return true;
            }

            return false;
        }
    }
}
