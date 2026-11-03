<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRentalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_name'  => 'nullable|string|max:255',
            'console_id'     => 'required|exists:consoles,id',
            'employee_id'    => 'required|exists:employees,id',
            'duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'notes'          => 'nullable|string|max:500',
            'pay_upfront'    => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:cash,qris,transfer,debit',
            'member_id'      => 'nullable|exists:members,id',
        ];
    }
}
