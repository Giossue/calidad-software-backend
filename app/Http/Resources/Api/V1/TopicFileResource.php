<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TopicFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TopicFile */
class TopicFileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_at' => $this->created_at?->toISOString(),
        ];
    }
}
