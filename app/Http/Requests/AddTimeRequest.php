<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTimeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'added_minutes'    => 'required|integer|min:1',
            'additional_price' => 'required|numeric|min:0',
            'notes'            => 'nullable|string|max:300',
        ];
    }
}
