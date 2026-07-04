<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    public function incoming(Request $request): JsonResponse
    {
        $from = $request->input('From');
        $body = trim($request->input('Body', ''));
        $messageSid = $request->input('MessageSid');

        Log::channel('stack')->info('Twilio WhatsApp reçu', [
            'from' => $from,
            'body' => $body,
            'sid'  => $messageSid,
        ]);

        if (empty($from) || empty($body)) {
            return response()->json(['success' => false, 'message' => 'Données incomplètes'], 422);
        }

        $phone = $this->normalizePhone($from);
        $user = User::with('role')
            ->whereHas('role', fn($q) => $q->where('nom', 'parent'))
            ->where(function ($q) use ($phone) {
                $q->where('telephone', $phone)
                  ->orWhere('telephone_2', $phone);
            })
            ->first();

        if (!$user) {
            Log::channel('stack')->warning('Twilio: numéro non reconnu', ['from' => $from, 'phone' => $phone]);
            return response()->json(['success' => false, 'message' => 'Numéro non reconnu'], 404);
        }

        Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
            'type'      => 'message_whatsapp',
            'titre'     => 'Message WhatsApp reçu',
            'message'   => $body,
        ]);

        $this->firebase->notifyUser($user->id, '📩 Message WhatsApp', $body, [
            'type' => 'message_whatsapp',
            'from' => $from,
        ]);

        return response()->json(['success' => true]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 12 && str_starts_with($phone, '213')) {
            return $phone;
        }
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '213' . substr($phone, 1);
        }
        return $phone;
    }
}
