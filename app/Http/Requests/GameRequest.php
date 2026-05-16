<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'genre'         => 'nullable|string|max:100',
            'is_multiplayer' => 'boolean',
        ];
    }
}
