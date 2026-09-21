<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>$this->id,
            'title'=>$this->title,
            'author'=>$this->author,
            'category'=>$this->category,
            'description'=>$this->description,
            'total_copies'=>$this->total_copies,
            'available_copies'=>$this->available_copies,
            'created_at' => $this->created_at->toDateTimeString(),
            ];
    }
}
