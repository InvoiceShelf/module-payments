<?php

namespace Modules\Payments\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payments\Helpers\VersionHelper;

if (VersionHelper::checkAppVersion('<', '2.0.0')) {
    VersionHelper::aliasClass('InvoiceShelf\Models\PaymentMethod', 'App\Models\PaymentMethod');
}

class PaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $data = [
            'name' => [
                'required',
                Rule::unique('payment_methods')
                    ->where('company_id', $this->header('company')),
            ],
            'driver' => [
                'required',
            ],
            'active' => [
                'required',
                'boolean',
            ],
            'use_test_env' => [
                'required',
                'boolean',
            ],
            'settings' => [
                'required',
            ],
            'settings.*' => [
                'required',
            ],
        ];

        if ($this->getMethod() == 'PUT') {
            $data['name'] = [
                'required',
                Rule::unique('payment_methods')
                    ->ignore($this->route('payment_provider'), 'id')
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $data;
    }

    public function getPaymentMethodPayload()
    {
        return collect($this->validated())
            ->merge([
                'type' => PaymentMethod::TYPE_MODULE,
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }
}
