<?php

return [
    'dsn' => env('SENTRY_DSN', ''),

    'environment' => env('APP_ENV', 'production'),

    'release' => env('APP_VERSION', '1.0.0'),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),



    'send_default_pii' => false,

    'breadcrumbs' => [
        'logs'              => true,
        'sql_queries'       => true,
        'sql_bindings'      => false,
        'queue_info'        => true,
        'command_info'      => true,
    ],

    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ],

    'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
        if (empty(env('SENTRY_DSN'))) return null;

        $tenantId = config('tenant.current_id');
        if ($tenantId) {
            $event->setTags(['tenant_id' => $tenantId]);
        }

        return $event;
    },
];
