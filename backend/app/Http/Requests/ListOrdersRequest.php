<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'affiliate_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:pending,approved,cancelled,refunded',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'min_value' => 'nullable|numeric|min:0',
            'max_value' => 'nullable|numeric|min:0|gte:min_value',
            'sort_by' => 'nullable|string|in:id,total_value,status,created_at,ordered_at',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
