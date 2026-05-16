<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'duration_hours' => 'required|integer|min:1',
            'price'          => 'required|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'is_active'      => 'boolean',
        ];
    }
}
