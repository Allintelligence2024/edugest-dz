<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationInAppController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $notifications = DB::table('notifications_inapp')
            ->where('tenant_id', config('tenant.current_id'))
            ->where('user_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        $nbNonLu = DB::table('notifications_inapp')
            ->where('tenant_id', config('tenant.current_id'))
            ->where('user_id', auth('api')->id())
            ->where('lu', false)
            ->count();

        return $this->paginatedResponse($notifications, 'Notifications récupérées', [
            'nb_non_lu' => $nbNonLu,
        ]);
    }

    public function marquerLue(string $id): JsonResponse
    {
        DB::table('notifications_inapp')
            ->where('id', $id)
            ->where('tenant_id', config('tenant.current_id'))
            ->where('user_id', auth('api')->id())
            ->update(['lu' => true, 'updated_at' => now()]);

        return $this->success(null, 'Notification marquée comme lue');
    }
}
