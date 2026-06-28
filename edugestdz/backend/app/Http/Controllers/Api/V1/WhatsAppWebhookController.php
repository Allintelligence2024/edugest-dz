<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse, Response};
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode         = $request->input('hub_mode');
        $token        = $request->input('hub_verify_token');
        $challenge    = $request->input('hub_challenge');
        $expectedToken = env('WHATSAPP_VERIFY_TOKEN', 'edugest_verify');

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge !== null) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (isset($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $value = $change['value'] ?? [];

                    if (isset($value['statuses'])) {
                        foreach ($value['statuses'] as $status) {
                            Log::channel('whatsapp')->info('Statut message WhatsApp', [
                                'message_id' => $status['id'],
                                'status'     => $status['status'],
                                'timestamp'  => $status['timestamp'] ?? null,
                                'recipient_id' => $status['recipient_id'] ?? null,
                            ]);
                        }
                    }

                    if (isset($value['messages'])) {
                        foreach ($value['messages'] as $message) {
                            Log::channel('whatsapp')->info('Message WhatsApp reçu', [
                                'message_id' => $message['id'],
                                'from'       => $message['from'],
                                'type'       => $message['type'],
                                'timestamp'  => $message['timestamp'] ?? null,
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['success' => true], 200);
    }
}
