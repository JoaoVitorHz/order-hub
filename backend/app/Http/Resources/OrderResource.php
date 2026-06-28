<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'total_value' => (float) $this->total_value,
            'ordered_at' => $this->ordered_at?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'valid_transitions' => $this->getValidTransitions(),
            'affiliate' => $this->whenLoaded('affiliate', fn () => [
                'id' => $this->affiliate->id,
                'name' => $this->affiliate->name,
                'email' => $this->affiliate->email,
                'phone' => $this->affiliate->phone ?? null,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) ($item->quantity * $item->price),
                'product' => $item->relationLoaded('product') ? [
                    'id' => $item->product->id,
                    'title' => $item->product->title,
                    'image' => $item->product->image,
                    'category' => $item->product->category,
                ] : null,
            ])),
            'status_logs' => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'changed_by' => $log->changed_by,
                'notes' => $log->notes,
                'changed_at' => $log->changed_at->toIso8601String(),
            ])),
        ];
    }
}
