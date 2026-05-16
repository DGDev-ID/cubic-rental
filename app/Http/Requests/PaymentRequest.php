<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'payments'          => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,qris',
            'payments.*.amount' => 'required|numeric|min:1',
            'payments.*.notes'  => 'nullable|string|max:300',
        ];
    }
}
