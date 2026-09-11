<?php

namespace App\Http\Middleware;

use App\Services\NotificationTimingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationTimingMiddleware
{
    public function __construct(
        private NotificationTimingService $timing,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge([
            'notification_timing' => [
                'en_plage_horaire'   => $this->timing->estEnPlageHoraire(),
                'en_heures_nuit'     => $this->timing->estEnHeuresNuit(),
                'delai_avant_envoi'  => $this->timing->getDelaiAvantEnvoi(),
                'peut_push'          => $this->timing->doitEnvoyerPush(),
                'peut_sms'           => $this->timing->doitEnvoyerSMS(),
                'peut_email'         => $this->timing->doitEnvoyerEmail(),
            ],
        ]);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Notification-Timing', $this->timing->getPlageHoraireLabel());
        }

        return $response;
    }
}
