<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BookResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'is_overdue'   => $this->isOverdue(),
            'borrowed_at'  => $this->borrowed_at?->toIso8601String(),
            'due_at'       => $this->due_at?->toIso8601String(),
            'returned_at'  => $this->returned_at?->toIso8601String(),

            'book' => BookResource::make($this->whenLoaded('book')),
            'member' => MemberResource::make($this->whenLoaded('member')),
        ];
    }
}