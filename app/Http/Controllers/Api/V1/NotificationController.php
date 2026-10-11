<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Avisos de acciones del usuario autenticado (campana del sistema).
 */
class NotificationController extends Controller
{
    /** Las notificaciones leídas se conservan este número de días. */
    public const READ_RETENTION_DAYS = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $user->readNotifications()->where('read_at', '<', now()->subDays(self::READ_RETENTION_DAYS))->delete();

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => collect($notifications->items())->map(fn (DatabaseNotification $notification): array => self::present($notification))->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $this->user($request)->unreadNotifications()->count()]]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        /** @var DatabaseNotification $found */
        $found = $this->user($request)->notifications()->whereKey($notification)->firstOrFail();
        $found->markAsRead();

        return response()->json(['data' => self::present($found)]);
    }

    public function markAllAsRead(Request $request): Response
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->getKey(),
            'kind' => $notification->data['kind'] ?? 'general',
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): Usuario
    {
        /** @var Usuario */
        return $request->user();
    }
}
