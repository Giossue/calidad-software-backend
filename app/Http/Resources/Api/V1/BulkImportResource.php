<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BulkImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BulkImport */
class BulkImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'created_count' => $this->created_count,
            'skipped_count' => $this->skipped_count,
            'failed_count' => $this->failed_count,
            'errors' => $this->errors ?? [],
            'warnings' => $this->warnings ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
