<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property int $user_id
 * @property string $status
 * @property int $total_rows
 * @property int $created_count
 * @property int $failed_count
 * @property array<int, array{row: int, messages: array<int, string>}>|null $errors
 * @property array<int, array{line: int, values: array<string, string|null>}>|null $rows
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'user_id', 'status', 'total_rows', 'created_count', 'failed_count', 'errors', 'rows'])]
#[Hidden(['rows'])]
class BulkImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** @return BelongsTo<Usuario, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'id_usuario');
    }

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'rows' => 'encrypted:array',
        ];
    }
}
