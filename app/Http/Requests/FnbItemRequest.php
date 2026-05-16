<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FnbItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'category' => 'required|in:food,drink,snack',
            'price'    => 'required|numeric|min:0',
        ];
    }
}
