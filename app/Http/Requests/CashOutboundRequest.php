<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashOutboundRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'nominal'     => 'required|numeric|min:1',
            'notes'       => 'required|string|max:500',
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
        ];
    }
}
