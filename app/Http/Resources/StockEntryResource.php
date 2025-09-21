<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StockEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'quantity'      => $this->quantity,
            'unit_cost'     => $this->unit_cost,
            'total_cost'    => $this->total_cost,
            'reason'        => $this->reason,
            'track_inventory' => (bool) $this->track_inventory,

            // Format the important dates with Carbon
            'effective_at'  => $this->effective_at
                                ? Carbon::parse($this->effective_at)->format('M d, Y h:i A')
                                : null,
            'created_at'    => $this->created_at
                                ? $this->created_at->format('M d, Y h:i A')
                                : null,
            'updated_at'    => $this->updated_at
                                ? $this->updated_at->format('M d, Y h:i A')
                                : null,

            // Relationships
            'variant' => [
                'id'     => $this->variant->id,
                'sku'    => $this->variant->sku,
                'product'=> $this->variant->product->name ?? null,
            ],
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id'   => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ];
            }),
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id'   => $this->employee->id,
                    'name' => $this->employee->name,
                ];
            }),

            // Optional note
            'note' => $this->note,
        ];
    }
}
