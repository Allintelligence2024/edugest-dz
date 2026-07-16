# Sentry Setup — EduGest DZ

## Vue d'ensemble

Sentry capture les erreurs et exceptions en temps réel pour l'application EduGest DZ.
Ce document explique comment configurer Sentry pour le monitoring des erreurs.

---

## 1. Créer un compte Sentry

1. Aller sur https://sentry.io et créer un compte
2. Créer un nouveau projet Laravel
3. Copier le **DSN** (Data Source Name) — format : `https://xxxxx@sentry.io/xxxxx`

---

## 2. Variables d'environnement

### 2.1 Variables Railway (Production)

| Variable | Description | Valeur |
|---|---|---|
| `SENTRY_DSN` | DSN Sentry | `https://xxxxx@sentry.io/xxxxx` |
| `SENTRY_TRACES_SAMPLE_RATE` | Taux de traces | `0.1` (10%) |
| `APP_ENV` | Environnement | `production` |

### 2.2 Configuration locale

Dans `.env` (développement local) :
```
SENTRY_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
```

> Laisser `SENTRY_DSN` vide en local pour désactiver Sentry.

---

## 3. Configuration dans le code

### 3.1 bootstrap/app.php

Le guard Sentry est déjà configuré dans `bootstrap/app.php` :

```php
// ── Sentry : reporter les exceptions en production ────────────
if (!empty(config('sentry.dsn')) && app()->environment('production', 'staging')) {
    $exceptions->report(function (\Throwable $e) {
        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    });
}
```

Cette configuration :
- ✅ Vérifie que `SENTRY_DSN` n'est pas vide
- ✅ Vérifie que l'environnement est `production` ou `staging`
- ✅ Vérifie que le service Sentry est lié (`app()->bound`)
- ✅ Ne plante jamais si Sentry n'est pas configuré

### 3.2 config/sentry.php

```php
return [
    'dsn' => env('SENTRY_DSN', ''),
    'environment' => env('APP_ENV', 'production'),
    'release' => env('APP_VERSION', '1.0.0'),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'send_default_pii' => false,
    'ignore_exceptions' => [
        // Exceptions non critiques ignorées
    ],
];
```

---

## 4. Alertes Sentry

### 4.1 Alertes par email

1. Aller dans Sentry → **Alerts** → **Create Alert Rule**
2. Configurer :
   - **Alert type** : Issue alert
   - **Trigger** : When a new issue is created
   - **Action** : Send a notification (email)
   - **Destination** : emails de l'équipe

### 4.2 Alertes Slack (optionnel)

1. Installer l'intégration Slack dans Sentry
2. Configurer le canal Slack (#edugest-alertes)
3. Ajouter une action d'alerte Slack

### 4.3 Alertes critiques

Créer des alertes séparées pour :
- **Erreurs 500** : Taux d'erreur > 1% en 5 minutes
- **Requêtes lentes** : Temps de réponse > 5 secondes
- **Erreurs nouvelles** : Nouvelles erreurs non résolues

---

## 5. Environnements

| Environnement | Sentry DSN | Traces | Alertes |
|---|---|---|---|
| Production | `https://xxx@sentry.io/xxx` | 10% | Email + Slack |
| Staging | `https://xxx@sentry.io/xxx` | 50% | Email |
| Développement | `""` (vide) | 0% | Non |

---

## 6. Bonnes pratiques

- Ne jamais envoyer de données personnelles (PII) à Sentry
- Utiliser les tags pour contexte : `user_id`, `tenant_id`, `role`
- Résoudre les erreurs critiques avant les mineures
- Revérifier les alertes chaque matin
- Archiver les erreurs résolues régulièrement

---

*Dernière mise à jour : 2026-07-16*
