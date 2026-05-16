<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:regular,vip,vvip,suite',
            'price_per_hour' => 'required|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'status'         => 'required|in:available,occupied,maintenance,inactive',
            'game_ids'       => 'nullable|array',
            'game_ids.*'     => 'exists:games,id',
        ];
    }
}
