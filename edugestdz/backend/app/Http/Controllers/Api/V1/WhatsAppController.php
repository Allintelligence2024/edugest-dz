<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => 'required|string',
            'message' => 'required_without:template|string',
            'template' => 'required_without:message|string',
            'parameters' => 'nullable|array',
        ]);

        if ($request->has('template')) {
            $result = $this->whatsapp->sendTemplate(
                $request->input('to'),
                $request->input('template'),
                $request->input('parameters', [])
            );
        } else {
            $result = $this->whatsapp->sendText(
                $request->input('to'),
                $request->input('message')
            );
        }

        if ($result['success']) {
            WhatsappMessage::create([
                'message_id'  => $result['messageId'],
                'to_number'   => $result['to'],
                'direction'   => 'out',
                'type'        => $request->has('template') ? 'template' : 'text',
                'content'     => $request->input('message'),
                'template_name' => $request->input('template'),
                'status'      => 'sent',
            ]);

            return response()->json(['success' => true, 'data' => $result], 201);
        }

        return response()->json(['success' => false, 'error' => $result['error']], 422);
    }

    public function index(Request $request): JsonResponse
    {
        $messages = WhatsappMessage::orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function show(string $id): JsonResponse
    {
        $message = WhatsappMessage::findOrFail($id);

        return response()->json(['success' => true, 'data' => $message]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total'   => WhatsappMessage::count(),
            'sent'    => WhatsappMessage::where('direction', 'out')->where('status', 'sent')->count(),
            'failed'  => WhatsappMessage::where('status', 'failed')->count(),
            'incoming' => WhatsappMessage::where('direction', 'in')->count(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
