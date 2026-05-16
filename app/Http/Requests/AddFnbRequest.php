<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddFnbRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'items'                 => 'required|array|min:1',
            'items.*.fnb_item_id'   => 'required|exists:fnb_items,id',
            'items.*.qty'           => 'required|integer|min:1',
            'items.*.addons'        => 'nullable|array',
            'items.*.addons.*.addon_id' => 'required|exists:fnb_addons,id',
            'items.*.addons.*.name'     => 'required|string',
            'items.*.addons.*.price'    => 'required|numeric',
        ];
    }
}
