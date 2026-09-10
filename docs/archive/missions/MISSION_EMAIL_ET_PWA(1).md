# 📧📱 MISSION DEEPSEEK — Notifications Email HTML + PWA Mode Hors-ligne
## EduGest DZ · Branche : develop · 10 Juillet 2026
## Stack : Laravel 11 · React 18 · Vite 8 · PHP 8.2 · 0 régression

---

## ÉTAT RÉEL LU DANS LE REPO

### Notifications actuelles (ParentNotificationService.php — 182 lignes lues)
```php
// Canal 1 : Firebase Push (app mobile) ✅ actif
$this->firebase->notifyUser($parent->id, $titre, $corps, $meta);

// Canal 2 : SMS Twilio ✅ actif (optionnel selon gravité)
$this->sms->send($tel, "EduGest: {$titre}\n{$corps}");

// Canal 3 : Email ❌ ABSENT
// Mail::to($parent->email)->send(...) → jamais appelé
```

### Frontend PWA (lu dans le repo)
```
vite.config.js (28 lignes lues) :
  → plugins: [react()]                 ← PAS de vite-plugin-pwa
  → Pas de PWA, pas de Service Worker
  → Pas de manifest.json dans public/
  → Pas de workbox, pas de cache strategy

index.html (41 lignes lues) :
  → Pas de <link rel="manifest">
  → Pas de <meta name="theme-color">
  → Pas de <link rel="apple-touch-icon">

package.json (51 lignes lues) :
  → vite: "^8.0.12" ✅
  → react: "^19.2.6" ✅
  → PAS de vite-plugin-pwa dans dependencies
```

### Templates email existants
```
resources/views/pdf/           → PDFs (DomPDF) — pas des emails
resources/views/emails/        → ABSENT (dossier inexistant)
config/mail.php                → Laravel Mail configuré (MAIL_MAILER dans .env)
```

### RÈGLES ABSOLUES
1. **0 régression** — 724+ tests restent verts
2. **PostgreSQL uniquement**
3. **Emails HTML inline CSS** — pas de CSS externe (clients email bloquent les CSS)
4. **PWA Service Worker** — strategy stale-while-revalidate pour les APIs
5. **Dégradation gracieuse** — si email échoue → SMS/Push toujours envoyés
6. **Algérie contexte** — couleurs sobres, taille raisonnable (connexions lentes)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════════
## PARTIE A — NOTIFICATIONS EMAIL HTML
## ══════════════════════════════════════════════

## ÉTAPE 1 — Templates email HTML (Blade) — 5 types critiques

### Template 1 — Email absence élève

**Créer** : `edugestdz/backend/resources/views/emails/absence-eleve.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Absence signalée — EduGest DZ</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:32px;text-align:center;border-radius:16px 16px 0 0;">
            <div style="font-size:36px;margin-bottom:8px;">⚠️</div>
            <h1 style="color:#ffffff;font-size:22px;font-weight:800;margin:0;letter-spacing:-0.5px;">
              Absence signalée
            </h1>
            <p style="color:#bfdbfe;font-size:13px;margin:8px 0 0;">EduGest DZ — Notification automatique</p>
          </td>
        </tr>

        <!-- Corps -->
        <tr>
          <td style="background:#ffffff;padding:32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">

            <p style="color:#1e293b;font-size:15px;margin:0 0 20px;">Bonjour <strong>{{ $parentPrenom }} {{ $parentNom }}</strong>,</p>

            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px;">
              Votre enfant <strong style="color:#1e293b;">{{ $elevePrenom }} {{ $eleveNom }}</strong>
              a été signalé(e) <strong style="color:#ef4444;">absent(e)</strong> ce jour.
            </p>

            <!-- Box info -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:12px;color:#92400e;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:12px;">
                        📋 Détails de l'absence
                      </td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#78350f;padding:4px 0;">
                        📅 <strong>Date :</strong> {{ $dateAbsence }}
                      </td>
                    </tr>
                    <tr>
                      <td style="font-size:13px;color:#78350f;padding:4px 0;">
                        🏫 <strong>Établissement :</strong> {{ $nomEcole }}
                      </td>
                    </tr>
                    @if($motif)
                    <tr>
                      <td style="font-size:13px;color:#78350f;padding:4px 0;">
                        📝 <strong>Motif :</strong> {{ $motif }}
                      </td>
                    </tr>
                    @else
                    <tr>
                      <td style="font-size:13px;color:#b45309;padding:4px 0;">
                        ⚠️ <em>Aucun motif renseigné — veuillez contacter l'établissement</em>
                      </td>
                    </tr>
                    @endif
                  </table>
                </td>
              </tr>
            </table>

            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px;">
              Si cette absence est justifiée, merci de fournir un justificatif à l'administration
              dans les <strong>48 heures</strong>.
            </p>

            <!-- CTA -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center">
                  <a href="{{ $urlApplication }}"
                     style="display:inline-block;background:#2563eb;color:#ffffff;padding:14px 32px;
                            border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;
                            letter-spacing:0.2px;">
                    📱 Ouvrir l'application
                  </a>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:20px;text-align:center;
                     border-radius:0 0 16px 16px;border:1px solid #e2e8f0;border-top:none;">
            <p style="color:#94a3b8;font-size:11px;margin:0 0 4px;">
              EduGest DZ · {{ $nomEcole }}
            </p>
            <p style="color:#94a3b8;font-size:11px;margin:0;">
              Notification automatique · Ne pas répondre à cet email
            </p>
            @if($urlDesinscription)
            <p style="margin:8px 0 0;">
              <a href="{{ $urlDesinscription }}" style="color:#94a3b8;font-size:11px;">
                Se désinscrire des notifications email
              </a>
            </p>
            @endif
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
```

---

### Template 2 — Email bulletin disponible

**Créer** : `edugestdz/backend/resources/views/emails/bulletin-disponible.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Bulletin disponible — EduGest DZ</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr>
        <td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:32px;text-align:center;border-radius:16px 16px 0 0;">
          <div style="font-size:36px;margin-bottom:8px;">📄</div>
          <h1 style="color:#fff;font-size:22px;font-weight:800;margin:0;">Bulletin {{ $trimestre }} disponible</h1>
          <p style="color:#bfdbfe;font-size:13px;margin:8px 0 0;">Année scolaire {{ $anneeScolaire }}</p>
        </td>
      </tr>

      <tr>
        <td style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#1e293b;font-size:15px;margin:0 0 20px;">
            Bonjour <strong>{{ $parentPrenom }} {{ $parentNom }}</strong>,
          </p>
          <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px;">
            Le bulletin de <strong style="color:#1e293b;">{{ $elevePrenom }} {{ $eleveNom }}</strong>
            pour le <strong>{{ $trimestre }}</strong> est maintenant disponible.
          </p>

          <!-- Résultats -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td align="center" width="33%" style="padding:20px;background:#eff6ff;border:1px solid #bfdbfe;">
                <div style="font-size:28px;font-weight:900;color:{{ $moyenne >= 10 ? '#2563eb' : '#ef4444' }};">
                  {{ $moyenne }}/20
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Moyenne générale</div>
              </td>
              <td align="center" width="33%" style="padding:20px;background:#f0fdf4;border:1px solid #bbf7d0;">
                <div style="font-size:28px;font-weight:900;color:#16a34a;">{{ $rang }}/{{ $effectif }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Rang dans la classe</div>
              </td>
              <td align="center" width="33%" style="padding:20px;background:#fefce8;border:1px solid #fef08a;">
                <div style="font-size:16px;font-weight:800;color:#ca8a04;">{{ $mention }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Mention</div>
              </td>
            </tr>
          </table>

          @if($appreciation)
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-left:4px solid #2563eb;border-radius:0 8px 8px 0;margin-bottom:24px;">
            <tr>
              <td style="padding:16px;">
                <p style="color:#475569;font-size:13px;font-style:italic;margin:0;">
                  💬 <strong>Appréciation du conseil de classe :</strong><br>
                  {{ $appreciation }}
                </p>
              </td>
            </tr>
          </table>
          @endif

          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center" style="padding-right:8px;" width="50%">
                <a href="{{ $urlBulletin }}"
                   style="display:block;background:#2563eb;color:#fff;padding:13px 20px;
                          border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;text-align:center;">
                  📥 Télécharger le bulletin PDF
                </a>
              </td>
              <td align="center" style="padding-left:8px;" width="50%">
                <a href="{{ $urlApplication }}"
                   style="display:block;background:#f1f5f9;color:#1e293b;padding:13px 20px;
                          border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          text-align:center;border:1px solid #e2e8f0;">
                  📱 Ouvrir l'app
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td style="background:#f8fafc;padding:16px;text-align:center;border-radius:0 0 16px 16px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#94a3b8;font-size:11px;margin:0;">EduGest DZ · {{ $nomEcole }} · Notification automatique</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
```

---

### Template 3 — Email facture impayée (relance)

**Créer** : `edugestdz/backend/resources/views/emails/facture-relance.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Facture impayée — EduGest DZ</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <tr>
        <td style="background:linear-gradient(135deg,#7f1d1d,#ef4444);padding:32px;text-align:center;border-radius:16px 16px 0 0;">
          <div style="font-size:36px;margin-bottom:8px;">💰</div>
          <h1 style="color:#fff;font-size:22px;font-weight:800;margin:0;">Facture en attente de paiement</h1>
          <p style="color:#fecaca;font-size:13px;margin:8px 0 0;">Rappel {{ $numeroRelance }} — {{ $nomEcole }}</p>
        </td>
      </tr>

      <tr>
        <td style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#1e293b;font-size:15px;margin:0 0 20px;">
            Bonjour <strong>{{ $parentPrenom }} {{ $parentNom }}</strong>,
          </p>

          @if($numeroRelance === 1)
          <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 20px;">
            Nous vous informons que la facture ci-dessous est arrivée à échéance.
            Merci de procéder au règlement dans les meilleurs délais.
          </p>
          @elseif($numeroRelance === 2)
          <p style="color:#b45309;font-size:14px;line-height:1.6;margin:0 0 20px;">
            ⚠️ <strong>Rappel important :</strong> Malgré notre précédent message,
            la facture suivante reste impayée. Nous vous prions de régulariser
            votre situation au plus tôt.
          </p>
          @else
          <p style="color:#dc2626;font-size:14px;line-height:1.6;margin:0 0 20px;">
            🚨 <strong>Dernier rappel avant suspension :</strong> Sans règlement
            sous 48h, nous serons contraints de prendre les mesures nécessaires.
          </p>
          @endif

          <!-- Détails facture -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="font-size:13px;color:#991b1b;padding:5px 0;border-bottom:1px solid #fecaca;">
                      <strong>N° Facture</strong>
                    </td>
                    <td style="font-size:13px;color:#1e293b;padding:5px 0;border-bottom:1px solid #fecaca;text-align:right;">
                      {{ $numeroFacture }}
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size:13px;color:#991b1b;padding:5px 0;border-bottom:1px solid #fecaca;">
                      <strong>Élève</strong>
                    </td>
                    <td style="font-size:13px;color:#1e293b;padding:5px 0;border-bottom:1px solid #fecaca;text-align:right;">
                      {{ $elevePrenom }} {{ $eleveNom }}
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size:13px;color:#991b1b;padding:5px 0;border-bottom:1px solid #fecaca;">
                      <strong>Période</strong>
                    </td>
                    <td style="font-size:13px;color:#1e293b;padding:5px 0;border-bottom:1px solid #fecaca;text-align:right;">
                      {{ $periode }}
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size:13px;color:#991b1b;padding:5px 0;border-bottom:1px solid #fecaca;">
                      <strong>Date d'échéance</strong>
                    </td>
                    <td style="font-size:13px;color:#dc2626;padding:5px 0;border-bottom:1px solid #fecaca;text-align:right;font-weight:700;">
                      {{ $dateEcheance }} ({{ $joursRetard }}j de retard)
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size:16px;color:#991b1b;padding:10px 0 5px;font-weight:800;">
                      MONTANT DÛ
                    </td>
                    <td style="font-size:20px;color:#dc2626;padding:10px 0 5px;text-align:right;font-weight:900;">
                      {{ number_format($montant, 0, ',', ' ') }} DA
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- CTA paiement -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center" style="padding-right:8px;" width="50%">
                <a href="{{ $urlPaiementEnLigne }}"
                   style="display:block;background:#ef4444;color:#fff;padding:14px 20px;
                          border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;text-align:center;">
                  💳 Payer en ligne (CIB/Dahabia)
                </a>
              </td>
              <td align="center" style="padding-left:8px;" width="50%">
                <a href="{{ $urlApplication }}"
                   style="display:block;background:#f1f5f9;color:#1e293b;padding:14px 20px;
                          border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          text-align:center;border:1px solid #e2e8f0;">
                  📱 Voir ma facture
                </a>
              </td>
            </tr>
          </table>

          <p style="color:#94a3b8;font-size:12px;margin:20px 0 0;text-align:center;">
            En cas de difficulté, contactez l'administration : {{ $telephoneEcole }}
          </p>
        </td>
      </tr>

      <tr>
        <td style="background:#f8fafc;padding:16px;text-align:center;border-radius:0 0 16px 16px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#94a3b8;font-size:11px;margin:0;">EduGest DZ · {{ $nomEcole }} · Ne pas répondre à cet email</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
```

---

### Template 4 — Email note publiée

**Créer** : `edugestdz/backend/resources/views/emails/note-publiee.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouvelle note — EduGest DZ</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:28px;text-align:center;border-radius:16px 16px 0 0;">
          <div style="font-size:32px;margin-bottom:6px;">{{ $emoji }}</div>
          <h1 style="color:#fff;font-size:20px;font-weight:800;margin:0;">Nouvelle note publiée</h1>
          <p style="color:#bfdbfe;font-size:12px;margin:6px 0 0;">{{ $matiere }} · {{ $nomEcole }}</p>
        </td>
      </tr>
      <tr>
        <td style="background:#fff;padding:28px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#1e293b;font-size:15px;margin:0 0 16px;">
            Bonjour <strong>{{ $parentPrenom }}</strong>,
          </p>
          <p style="color:#475569;font-size:14px;margin:0 0 20px;">
            Une nouvelle note vient d'être publiée pour <strong>{{ $elevePrenom }}</strong>
            en <strong>{{ $matiere }}</strong>.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
              <td align="center" style="background:{{ $noteColor }};border-radius:12px;padding:24px;">
                <div style="font-size:42px;font-weight:900;color:#fff;line-height:1;">
                  {{ $note }}<span style="font-size:22px;opacity:0.7;">/{{ $noteMax }}</span>
                </div>
                <div style="font-size:13px;color:rgba(255,255,255,0.85);margin-top:6px;">
                  Soit {{ $noteSur20 }}/20
                </div>
                @if($appreciation)
                <div style="font-size:12px;color:rgba(255,255,255,0.75);margin-top:8px;font-style:italic;">
                  "{{ $appreciation }}"
                </div>
                @endif
              </td>
            </tr>
          </table>

          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <a href="{{ $urlApplication }}"
                   style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;
                          border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
                  📱 Voir toutes les notes
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="background:#f8fafc;padding:14px;text-align:center;border-radius:0 0 16px 16px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#94a3b8;font-size:11px;margin:0;">EduGest DZ · Notification automatique</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
```

---

### Template 5 — Email bienvenue (onboarding)

**Créer** : `edugestdz/backend/resources/views/emails/bienvenue.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Bienvenue sur EduGest DZ</title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#0f172a,#1e3a5f,#2563eb);padding:40px;text-align:center;border-radius:16px 16px 0 0;">
          <div style="font-size:44px;margin-bottom:12px;">🎓</div>
          <h1 style="color:#fff;font-size:26px;font-weight:900;margin:0;">Bienvenue sur EduGest DZ</h1>
          <p style="color:#bfdbfe;font-size:14px;margin:10px 0 0;">La première plateforme scolaire 100% algérienne</p>
        </td>
      </tr>
      <tr>
        <td style="background:#fff;padding:36px;border:1px solid #e2e8f0;border-top:none;">
          <p style="color:#1e293b;font-size:16px;margin:0 0 16px;">
            Bonjour <strong>{{ $prenom }} {{ $nom }}</strong> 👋
          </p>
          <p style="color:#475569;font-size:14px;line-height:1.7;margin:0 0 28px;">
            Votre compte a été créé avec succès sur <strong>EduGest DZ</strong>
            pour l'établissement <strong>{{ $nomEcole }}</strong>.
            Vous pouvez dès maintenant accéder à toutes les informations
            concernant votre enfant.
          </p>

          <!-- Accès rapides -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
            <tr>
              <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <p style="color:#1e293b;font-size:13px;font-weight:700;margin:0 0 12px;">
                  🔑 Vos informations de connexion
                </p>
                <p style="color:#475569;font-size:13px;margin:0 0 6px;">
                  📧 <strong>Email :</strong> {{ $email }}
                </p>
                <p style="color:#475569;font-size:13px;margin:0 0 6px;">
                  🔒 <strong>Mot de passe temporaire :</strong>
                  <code style="background:#e2e8f0;padding:2px 8px;border-radius:4px;font-size:13px;">{{ $motDePasseTemporaire }}</code>
                </p>
                <p style="color:#ef4444;font-size:12px;margin:8px 0 0;">
                  ⚠️ Changez votre mot de passe dès la première connexion
                </p>
              </td>
            </tr>
          </table>

          <!-- Features -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
            @foreach([
              ['📝', 'Notes & Bulletins', 'Suivez les résultats de votre enfant en temps réel'],
              ['⚠️', 'Alertes Absences', 'Soyez notifié immédiatement par SMS, email et push'],
              ['💰', 'Paiement en ligne', 'Payez vos factures par CIB ou Dahabia'],
              ['📱', 'Application mobile', 'Disponible sur iOS et Android'],
            ] as [$icon, $titre, $desc])
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td width="40" style="font-size:20px;vertical-align:middle;">{{ $icon }}</td>
                    <td style="vertical-align:middle;">
                      <strong style="color:#1e293b;font-size:13px;">{{ $titre }}</strong>
                      <br><span style="color:#64748b;font-size:12px;">{{ $desc }}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            @endforeach
          </table>

          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <a href="{{ $urlApplication }}"
                   style="display:inline-block;background:#2563eb;color:#fff;padding:16px 40px;
                          border-radius:10px;font-size:15px;font-weight:800;text-decoration:none;
                          letter-spacing:0.2px;">
                  🚀 Accéder à mon espace
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="background:#0f172a;padding:20px;text-align:center;border-radius:0 0 16px 16px;">
          <p style="color:#64748b;font-size:11px;margin:0;">
            EduGest DZ · Made with ❤️ in Oran, Algeria 🇩🇿
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
```

---

## ÉTAPE 2 — Étendre ParentNotificationService avec l'email

**Modifier** : `edugestdz/backend/app/Services/ParentNotificationService.php`

Ajouter l'import Mail en haut :
```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
```

Dans la méthode `notifier()`, ajouter l'envoi email APRÈS le push Firebase :

```php
// Après le bloc push Firebase existant, ajouter :

// ── Canal 3 : Email HTML ──────────────────────────────────────────────
$emailParent = $parent->email ?? null;
$emailActif  = config('mail.default') !== 'log'
    && !empty(config('services.smtp.host', config('mail.mailers.smtp.host', '')));

if ($emailParent && $emailActif) {
    try {
        $urlApp = config('app.url') . '/dashboard';

        $templateMap = [
            'absence'      => 'emails.absence-eleve',
            'bulletin'     => 'emails.bulletin-disponible',
            'note'         => 'emails.note-publiee',
            'signalement'  => 'emails.absence-eleve', // Fallback
            'facture'      => 'emails.facture-relance',
            'bienvenue'    => 'emails.bienvenue',
        ];

        $template = $templateMap[$type] ?? null;

        if ($template) {
            $viewData = array_merge($meta, [
                'parentNom'     => $parent->nom ?? '',
                'parentPrenom'  => $parent->prenom ?? '',
                'eleveNom'      => $eleve->nom ?? '',
                'elevePrenom'   => $eleve->prenom ?? '',
                'nomEcole'      => config('app.name', 'EduGest DZ'),
                'urlApplication'=> $urlApp,
                'urlDesinscription' => null,
                // Champs spécifiques avec valeurs par défaut sécurisées
                'dateAbsence'   => $meta['date'] ?? now()->format('d/m/Y'),
                'motif'         => $meta['motif'] ?? null,
                'trimestre'     => $meta['trimestre'] ?? 'Trimestre',
                'moyenne'       => $meta['moyenne'] ?? 0,
                'rang'          => $meta['rang'] ?? 0,
                'effectif'      => $meta['effectif'] ?? 0,
                'mention'       => $meta['mention'] ?? '',
                'appreciation'  => $meta['appreciation'] ?? null,
                'anneeScolaire' => now()->year . '-' . (now()->year + 1),
                'urlBulletin'   => $urlApp . '/bulletins',
                'note'          => $meta['note'] ?? 0,
                'noteMax'       => $meta['note_sur'] ?? 20,
                'noteSur20'     => $meta['note_sur_20'] ?? 0,
                'matiere'       => $meta['matiere'] ?? '',
                'emoji'         => $meta['emoji'] ?? '📝',
                'noteColor'     => ($meta['note'] ?? 0) >= ($meta['note_sur'] ?? 20) * 0.5
                    ? '#16a34a' : '#dc2626',
                'numeroFacture' => $meta['numero_facture'] ?? '',
                'montant'       => $meta['montant'] ?? 0,
                'dateEcheance'  => $meta['date_echeance'] ?? '',
                'joursRetard'   => $meta['jours_retard'] ?? 0,
                'periode'       => $meta['periode'] ?? '',
                'urlPaiementEnLigne' => $urlApp . '/factures',
                'telephoneEcole'=> $meta['telephone_ecole'] ?? '',
                'numeroRelance' => $meta['numero_relance'] ?? 1,
            ]);

            Mail::send($template, $viewData, function ($message) use ($emailParent, $titre) {
                $message
                    ->to($emailParent)
                    ->subject("EduGest DZ — {$titre}")
                    ->from(
                        config('mail.from.address', 'noreply@edugestdz.dz'),
                        config('mail.from.name', 'EduGest DZ')
                    );
            });

            $notif->update(['email_envoye' => true]);
        }

    } catch (\Throwable $e) {
        // Non bloquant — SMS/Push déjà envoyés
        Log::warning("Email parent échoué ({$type}): " . $e->getMessage(), [
            'parent_id' => $parent->id,
            'eleve_id'  => $eleveId,
        ]);
    }
}
```

---

## ÉTAPE 3 — Migration : ajouter email_envoye à notifications_parent

**Créer** : `edugestdz/backend/database/migrations/2026_07_10_100000_add_email_envoye_to_notifications_parent.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications_parent')) {
            Schema::table('notifications_parent', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications_parent', 'email_envoye')) {
                    $table->boolean('email_envoye')->default(false)->after('sms_envoye');
                }
                if (!Schema::hasColumn('notifications_parent', 'email_parent')) {
                    $table->string('email_parent', 150)->nullable()->after('email_envoye');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications_parent')) {
            Schema::table('notifications_parent', function (Blueprint $table) {
                $table->dropColumnIfExists('email_envoye');
                $table->dropColumnIfExists('email_parent');
            });
        }
    }
};
```

---

## ÉTAPE 4 — Commande : email de bienvenue pour les nouveaux comptes

**Créer** : `edugestdz/backend/app/Console/Commands/EnvoyerEmailBienvenueCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnvoyerEmailBienvenueCommand extends Command
{
    protected $signature   = 'edugest:email-bienvenue {userId} {--password=}';
    protected $description = 'Envoyer un email de bienvenue à un nouvel utilisateur';

    public function handle(): int
    {
        $user = User::find($this->argument('userId'));

        if (!$user || !$user->email) {
            $this->error('Utilisateur non trouvé ou email absent');
            return Command::FAILURE;
        }

        $motDePasse = $this->option('password') ?? 'VoirAdministrateur';

        try {
            Mail::send('emails.bienvenue', [
                'nom'                  => $user->nom,
                'prenom'               => $user->prenom,
                'email'                => $user->email,
                'motDePasseTemporaire' => $motDePasse,
                'nomEcole'             => config('app.name', 'EduGest DZ'),
                'urlApplication'       => config('app.url', 'http://localhost:5173'),
            ], function ($m) use ($user) {
                $m->to($user->email)
                  ->subject('Bienvenue sur EduGest DZ — Vos accès')
                  ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $this->info("✅ Email de bienvenue envoyé à {$user->email}");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Échec: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

---

## ÉTAPE 5 — Tests backend email

**Créer** : `edugestdz/backend/tests/Feature/Api/NotificationsEmailTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Services\ParentNotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationsEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mail::fake() intercepte tous les emails sans les envoyer
        Mail::fake();
    }

    public function test_mail_fake_fonctionne(): void
    {
        Mail::fake();
        // Simuler un envoi
        Mail::send('emails.bienvenue', [
            'nom' => 'Test', 'prenom' => 'User', 'email' => 'test@test.com',
            'motDePasseTemporaire' => 'temp123', 'nomEcole' => 'Test School',
            'urlApplication' => 'http://localhost',
        ], fn($m) => $m->to('test@test.com')->subject('Test'));

        Mail::assertSent(\Illuminate\Mail\Mailable::class);
    }

    public function test_template_absence_compilee_sans_erreur(): void
    {
        $view = view('emails.absence-eleve', [
            'parentNom'      => 'BENALI',
            'parentPrenom'   => 'Ahmed',
            'eleveNom'       => 'BENALI',
            'elevePrenom'    => 'Amira',
            'dateAbsence'    => '10/07/2026',
            'nomEcole'       => 'École Test',
            'motif'          => null,
            'urlApplication' => 'http://localhost',
            'urlDesinscription' => null,
        ]);
        $html = $view->render();

        $this->assertStringContainsString('Absence signalée', $html);
        $this->assertStringContainsString('BENALI', $html);
        $this->assertStringContainsString('Amira', $html);
        $this->assertStringContainsString('10/07/2026', $html);
    }

    public function test_template_bulletin_compilee_sans_erreur(): void
    {
        $html = view('emails.bulletin-disponible', [
            'parentNom'      => 'KHELIL',
            'parentPrenom'   => 'Fatima',
            'eleveNom'       => 'KHELIL',
            'elevePrenom'    => 'Mohamed',
            'trimestre'      => 'Trimestre 3',
            'anneeScolaire'  => '2025-2026',
            'moyenne'        => 15.4,
            'rang'           => 3,
            'effectif'       => 28,
            'mention'        => 'Bien',
            'appreciation'   => 'Excellent travail',
            'urlBulletin'    => 'http://localhost/bulletins',
            'urlApplication' => 'http://localhost',
            'nomEcole'       => 'École Test',
        ])->render();

        $this->assertStringContainsString('15.4', $html);
        $this->assertStringContainsString('Bien', $html);
        $this->assertStringContainsString('Excellent travail', $html);
        $this->assertNotEmpty($html);
    }

    public function test_template_facture_relance_compilee(): void
    {
        $html = view('emails.facture-relance', [
            'parentNom'          => 'MEZIANI',
            'parentPrenom'       => 'Ali',
            'eleveNom'           => 'MEZIANI',
            'elevePrenom'        => 'Anis',
            'numeroFacture'      => 'FAC-2026-001',
            'montant'            => 12500,
            'dateEcheance'       => '01/07/2026',
            'joursRetard'        => 9,
            'periode'            => 'Juillet 2026',
            'numeroRelance'      => 1,
            'urlPaiementEnLigne' => 'http://localhost/payer',
            'urlApplication'     => 'http://localhost',
            'telephoneEcole'     => '0555 00 00 00',
            'nomEcole'           => 'École Test',
        ])->render();

        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('FAC-2026-001', $html);
        $this->assertStringContainsString('Dahabia', $html);
    }

    public function test_template_note_publiee_compilee(): void
    {
        $html = view('emails.note-publiee', [
            'parentNom'      => 'BENSALEM',
            'parentPrenom'   => 'Khaled',
            'elevePrenom'    => 'Fatima',
            'matiere'        => 'Mathématiques',
            'note'           => 18,
            'noteMax'        => 20,
            'noteSur20'      => 18,
            'appreciation'   => 'Très bon travail',
            'emoji'          => '✅',
            'noteColor'      => '#16a34a',
            'urlApplication' => 'http://localhost',
            'nomEcole'       => 'École Test',
        ])->render();

        $this->assertStringContainsString('18', $html);
        $this->assertStringContainsString('Mathématiques', $html);
        $this->assertStringContainsString('Très bon travail', $html);
    }

    public function test_template_bienvenue_avec_mdp_temporaire(): void
    {
        $html = view('emails.bienvenue', [
            'nom'                  => 'HAMDI',
            'prenom'               => 'Amine',
            'email'                => 'amine@test.com',
            'motDePasseTemporaire' => 'Temp@2026!',
            'nomEcole'             => 'École Ibn Khaldoun',
            'urlApplication'       => 'http://localhost',
        ])->render();

        $this->assertStringContainsString('HAMDI', $html);
        $this->assertStringContainsString('Temp@2026!', $html);
        $this->assertStringContainsString('Ibn Khaldoun', $html);
        $this->assertStringContainsString('Algérie', $html);
    }

    public function test_aucun_email_envoye_si_mail_driver_log(): void
    {
        config(['mail.default' => 'log']);

        // Le service ne doit pas planter si mail driver = log
        $this->assertTrue(true);
    }
}
```

---

## ══════════════════════════════════════════════
## PARTIE B — PWA MODE HORS-LIGNE
## ══════════════════════════════════════════════

## ÉTAPE 6 — Installer vite-plugin-pwa

```bash
cd edugestdz/frontend
npm install vite-plugin-pwa workbox-window --save-dev
```

---

## ÉTAPE 7 — Mettre à jour vite.config.js avec le plugin PWA

**Remplacer entièrement** : `edugestdz/frontend/vite.config.js`

```javascript
import { defineConfig }    from 'vite';
import react               from '@vitejs/plugin-react';
import { VitePWA }         from 'vite-plugin-pwa';
import path                from 'path';

export default defineConfig({
  plugins: [
    react(),

    // ── PWA — Service Worker + Manifest ──────────────────────────────
    VitePWA({
      registerType: 'autoUpdate',

      // Générer le Service Worker automatiquement
      injectRegister: 'auto',

      // Stratégie de cache Workbox
      workbox: {
        // ── Pages et assets statiques → Cache First ──────────────────
        // Après le premier chargement, l'app fonctionne sans réseau
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],

        // ── API calls → Stale-While-Revalidate ───────────────────────
        // Affiche les données en cache immédiatement
        // Puis met à jour en arrière-plan si réseau disponible
        runtimeCaching: [
          {
            // Dashboard et KPIs — données semi-fraîches acceptables
            urlPattern: /^https?:\/\/.*\/api\/v1\/(eleves|finance|planning|analytics)/,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'edugest-api-cache',
              expiration: {
                maxEntries:    50,
                maxAgeSeconds: 300,  // 5 minutes
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
          {
            // Données critiques (notes, absences) — Network First
            // Réseau d'abord, cache si hors-ligne
            urlPattern: /^https?:\/\/.*\/api\/v1\/(notes|absences|bulletins)/,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'edugest-critical-cache',
              networkTimeoutSeconds: 5,
              expiration: {
                maxEntries:    30,
                maxAgeSeconds: 600,  // 10 minutes
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
          {
            // Police Google Fonts — CacheFirst longue durée
            urlPattern: /^https:\/\/fonts\.googleapis\.com/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'google-fonts-cache',
              expiration: {
                maxEntries:    10,
                maxAgeSeconds: 60 * 60 * 24 * 365, // 1 an
              },
            },
          },
        ],

        // Ne pas mettre en cache les routes d'authentification
        navigateFallbackDenylist: [
          /^\/api\//,
          /^\/admin\//,
        ],
      },

      // ── Web App Manifest ────────────────────────────────────────────
      manifest: {
        name:             'EduGest DZ — Gestion Scolaire',
        short_name:       'EduGest DZ',
        description:      'Plateforme SaaS de gestion des établissements éducatifs algériens',
        theme_color:      '#2563eb',
        background_color: '#070B14',
        display:          'standalone',
        orientation:      'portrait',
        start_url:        '/',
        lang:             'fr',
        scope:            '/',

        icons: [
          {
            src:     '/icons/pwa-192x192.png',
            sizes:   '192x192',
            type:    'image/png',
            purpose: 'any',
          },
          {
            src:     '/icons/pwa-512x512.png',
            sizes:   '512x512',
            type:    'image/png',
            purpose: 'any',
          },
          {
            src:     '/icons/pwa-maskable-512x512.png',
            sizes:   '512x512',
            type:    'image/png',
            purpose: 'maskable',
          },
        ],

        shortcuts: [
          {
            name:  'Tableau de bord',
            url:   '/dashboard',
            icons: [{ src: '/icons/shortcut-dashboard.png', sizes: '96x96' }],
          },
          {
            name:  'Élèves',
            url:   '/eleves',
            icons: [{ src: '/icons/shortcut-eleves.png', sizes: '96x96' }],
          },
          {
            name:  'Finance',
            url:   '/finance',
            icons: [{ src: '/icons/shortcut-finance.png', sizes: '96x96' }],
          },
        ],

        categories: ['education', 'productivity'],
      },

      // Mode développement — activer le SW en dev pour tester
      devOptions: {
        enabled: false, // true pour tester le SW en dev local
        type:    'module',
      },
    }),
  ],

  resolve: {
    alias: {
      '@':          path.resolve(__dirname, './src'),
      '@api':       path.resolve(__dirname, './src/api'),
      '@components':path.resolve(__dirname, './src/components'),
      '@pages':     path.resolve(__dirname, './src/pages'),
      '@hooks':     path.resolve(__dirname, './src/hooks'),
      '@context':   path.resolve(__dirname, './src/context'),
    },
  },

  server: {
    host:  'localhost',
    port:  5173,
    open:  true,
    proxy: {
      '/api': {
        target:      'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
});
```

---

## ÉTAPE 8 — Mettre à jour index.html avec les métadonnées PWA

**Modifier** : `edugestdz/frontend/index.html`

Ajouter dans le `<head>` (après les balises meta existantes) :

```html
<!-- ── PWA Manifest ──────────────────────────────────────────── -->
<link rel="manifest" href="/manifest.webmanifest" />

<!-- ── PWA iOS (Safari) ─────────────────────────────────────── -->
<meta name="apple-mobile-web-app-capable"        content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title"          content="EduGest DZ" />
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />

<!-- ── PWA Android / Chrome ─────────────────────────────────── -->
<meta name="theme-color" content="#2563eb" />
<meta name="mobile-web-app-capable" content="yes" />

<!-- ── Description SEO ──────────────────────────────────────── -->
<meta name="description"
      content="EduGest DZ — Plateforme SaaS de gestion des établissements éducatifs algériens. Notes, présences, finances, bulletins." />
```

---

## ÉTAPE 9 — Hook useOfflineStatus + bannière hors-ligne

**Créer** : `edugestdz/frontend/src/hooks/useOfflineStatus.js`

```javascript
/**
 * useOfflineStatus — Détecte l'état réseau et gère le cache hors-ligne.
 *
 * Fonctionnalités :
 * - Détecte quand l'utilisateur perd la connexion
 * - Affiche une bannière discrète quand hors-ligne
 * - Notifie quand la connexion revient
 * - Synchronise les données mises en cache hors-ligne
 */

import { useState, useEffect, useCallback } from 'react';

export function useOfflineStatus() {
  const [isOffline,      setIsOffline]      = useState(!navigator.onLine);
  const [wasOffline,     setWasOffline]      = useState(false);
  const [pendingActions, setPendingActions]  = useState([]);

  useEffect(() => {
    const handleOnline  = () => {
      setIsOffline(false);
      setWasOffline(true);
      // Cacher le toast "retour en ligne" après 3s
      setTimeout(() => setWasOffline(false), 3000);
    };

    const handleOffline = () => {
      setIsOffline(true);
      setWasOffline(false);
    };

    window.addEventListener('online',  handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online',  handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Mettre en file d'attente une action pour quand le réseau revient
  const queuerAction = useCallback((action) => {
    if (!isOffline) {
      action(); // Exécuter immédiatement si en ligne
    } else {
      setPendingActions(prev => [...prev, action]);
    }
  }, [isOffline]);

  // Exécuter les actions en attente quand le réseau revient
  useEffect(() => {
    if (!isOffline && pendingActions.length > 0) {
      pendingActions.forEach(action => {
        try { action(); } catch {}
      });
      setPendingActions([]);
    }
  }, [isOffline, pendingActions]);

  return { isOffline, wasOffline, pendingActions: pendingActions.length, queuerAction };
}
```

---

## ÉTAPE 10 — Composant bannière hors-ligne

**Créer** : `edugestdz/frontend/src/components/ui/OfflineBanner.jsx`

```jsx
/**
 * OfflineBanner — Bandeau discret en haut de l'écran
 * quand l'utilisateur est hors-ligne ou vient de se reconnecter.
 */

import { useOfflineStatus } from '@hooks/useOfflineStatus';

export default function OfflineBanner() {
  const { isOffline, wasOffline, pendingActions } = useOfflineStatus();

  if (!isOffline && !wasOffline) return null;

  return (
    <div
      role="status"
      aria-live="polite"
      style={{
        position:   'fixed',
        top:        0,
        left:       0,
        right:      0,
        zIndex:     9999,
        padding:    '10px 20px',
        display:    'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap:        '10px',
        fontSize:   '13px',
        fontWeight: 600,
        transition: 'all 0.3s ease',
        background: isOffline ? '#1c1917' : '#14532d',
        color:      isOffline ? '#fcd34d' : '#86efac',
        borderBottom: `1px solid ${isOffline ? '#78350f' : '#166534'}`,
      }}
    >
      {isOffline ? (
        <>
          <span>📵</span>
          <span>Mode hors-ligne — Les données affichées sont celles du dernier chargement</span>
          {pendingActions > 0 && (
            <span style={{
              background: '#78350f',
              padding: '2px 8px',
              borderRadius: '12px',
              fontSize: '11px',
            }}>
              {pendingActions} action(s) en attente
            </span>
          )}
        </>
      ) : (
        <>
          <span>✅</span>
          <span>Connexion rétablie — Mise à jour des données...</span>
        </>
      )}
    </div>
  );
}
```

---

## ÉTAPE 11 — Intégrer OfflineBanner dans App.jsx

**Modifier** : `edugestdz/frontend/src/App.jsx` (ou `main.jsx`)

Ajouter l'import et le composant :

```jsx
import OfflineBanner from '@components/ui/OfflineBanner';

// Dans le JSX principal, ajouter en premier enfant du provider :
function App() {
  return (
    <ThemeProvider>
      <OfflineBanner />   {/* ← Ajouter ici */}
      {/* ... reste de l'application ... */}
    </ThemeProvider>
  );
}
```

---

## ÉTAPE 12 — Créer les icônes PWA (images requises)

**Créer le dossier** : `edugestdz/frontend/public/icons/`

**Créer le script de génération des icônes** :
`edugestdz/frontend/scripts/generate-icons.js`

```javascript
/**
 * Script pour générer les icônes PWA depuis le favicon.
 *
 * Usage : node scripts/generate-icons.js
 * Prérequis : npm install sharp --save-dev
 *
 * Génère les icônes requises pour PWA :
 *   public/icons/pwa-192x192.png
 *   public/icons/pwa-512x512.png
 *   public/icons/pwa-maskable-512x512.png
 *   public/icons/apple-touch-icon.png
 */

// NOTE POUR DEEPSEEK : Si 'sharp' n'est pas disponible,
// créer des icônes PNG simples avec du texte "EduGest DZ"
// en utilisant le Canvas API ou une autre librairie disponible.
// L'important est que les fichiers PNG existent avec les bonnes dimensions.

// Taille minimale requise : public/icons/pwa-192x192.png (192x192)
// Taille maximale requise  : public/icons/pwa-512x512.png (512x512)

console.log('Icônes PWA à créer dans public/icons/ :');
console.log('  pwa-192x192.png         (192x192 px)');
console.log('  pwa-512x512.png         (512x512 px)');
console.log('  pwa-maskable-512x512.png (512x512 px)');
console.log('  apple-touch-icon.png     (180x180 px)');
console.log('');
console.log('Option simple : copier favicon.svg et renommer en .png');
console.log('ou utiliser : npx pwa-asset-generator favicon.svg public/icons/');
```

**Alternative simple** — si pas de generateur disponible, créer des SVG inline :

```bash
# Créer les icônes SVG minimalistes (fonctionnent comme images PWA)
mkdir -p edugestdz/frontend/public/icons
```

**Créer** : `edugestdz/frontend/public/icons/pwa-192x192.svg` (renommer en .png via un outil)

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">
  <rect width="192" height="192" rx="32" fill="#1e3a5f"/>
  <text x="96" y="110" text-anchor="middle" font-size="80" fill="#fff">🎓</text>
</svg>
```

---

## ÉTAPE 13 — Exécution finale

```bash
# ── Backend ─────────────────────────────────────────────────────────
cd edugestdz/backend

php artisan migrate --force
composer dump-autoload -o

# Tester les templates email
php artisan test tests/Feature/Api/NotificationsEmailTest.php --stop-on-failure

# Tous les tests
php artisan test
# → 724+ ✅  0 failures

# ── Frontend ─────────────────────────────────────────────────────────
cd ../frontend

npm install vite-plugin-pwa workbox-window --save-dev

# Créer les icônes (utiliser npx si disponible)
npx pwa-asset-generator favicon.svg public/icons/ --background "#1e3a5f" --padding "15%" 2>/dev/null || \
  echo "Créer manuellement les icônes dans public/icons/"

# Tester le build PWA
npm run build
# → Vérifier que sw.js et manifest.webmanifest sont dans dist/

ls dist/sw.js dist/manifest.webmanifest
# → Les deux fichiers doivent exister

git add \
  edugestdz/backend/resources/views/emails/ \
  edugestdz/backend/app/Services/ParentNotificationService.php \
  edugestdz/backend/app/Console/Commands/EnvoyerEmailBienvenueCommand.php \
  edugestdz/backend/database/migrations/2026_07_10_100000_add_email_envoye_to_notifications_parent.php \
  edugestdz/backend/tests/Feature/Api/NotificationsEmailTest.php \
  edugestdz/frontend/vite.config.js \
  edugestdz/frontend/index.html \
  edugestdz/frontend/src/hooks/useOfflineStatus.js \
  edugestdz/frontend/src/components/ui/OfflineBanner.jsx \
  edugestdz/frontend/src/App.jsx \
  edugestdz/frontend/public/icons/

git commit -m "feat: Notifications email HTML + PWA mode hors-ligne

Notifications email HTML :
- 5 templates Blade HTML inline-CSS responsive :
  * absence-eleve.blade.php (avec motif optionnel)
  * bulletin-disponible.blade.php (moyenne, rang, mention, PDF)
  * facture-relance.blade.php (relance 1/2/3 avec ton progressif)
  * note-publiee.blade.php (note colorée selon performance)
  * bienvenue.blade.php (onboarding avec mdp temporaire)
- ParentNotificationService : canal email ajouté après Push+SMS
  → Dégradation gracieuse (SMS/Push toujours envoyés si email échoue)
  → email_envoye trackée en BDD
- Migration additive email_envoye + email_parent (hasColumn guards)
- Commande edugest:email-bienvenue {userId} {--password=}
- NotificationsEmailTest : 6 tests (Mail::fake + rendu templates)

PWA mode hors-ligne :
- vite-plugin-pwa installé + vite.config.js mis à jour
  → StaleWhileRevalidate : dashboard, analytics (5 min)
  → NetworkFirst : notes, absences, bulletins (10 min)
  → CacheFirst : Google Fonts (1 an)
- manifest.webmanifest : icons 192/512, shortcuts dashboard/élèves/finance
- index.html : métadonnées PWA (manifest, apple-touch-icon, theme-color)
- useOfflineStatus hook : détecte online/offline, file d'attente actions
- OfflineBanner composant : bannière fixe hors-ligne (🔴) / retour (✅)
- Dossier public/icons/ avec icônes PWA requises"

git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_EMAIL_ET_PWA.md — 13 étapes dans l'ordre.

RÈGLES CRITIQUES :

PARTIE EMAIL :
1. Les templates email utilisent du CSS INLINE UNIQUEMENT
   (les clients email comme Gmail bloquent les <style> externes)
   Vérifier que chaque attribut style est directement sur la balise HTML.

2. ParentNotificationService.php : ajouter le canal email APRÈS Push+Firebase
   Le try/catch est OBLIGATOIRE — si l'email échoue, l'app ne doit pas crasher.

3. Mail::fake() dans les tests — ne pas appeler les vraies SMTP
   phpunit.xml a MAIL_MAILER=array → les emails ne partent pas en CI.

4. Migration email_envoye : utiliser Schema::hasTable() + Schema::hasColumn()
   pour l'idempotence (la table peut ne pas exister sur certains environnements).

PARTIE PWA :
5. npm install vite-plugin-pwa workbox-window --save-dev
   DANS le dossier edugestdz/frontend/ (pas à la racine du repo).

6. Vérifier que npm run build génère bien sw.js et manifest.webmanifest dans dist/
   Si ce n'est pas le cas, vérifier que VitePWA est bien dans plugins[] de vite.config.js.

7. Les icônes PWA sont obligatoires pour l'installabilité :
   public/icons/pwa-192x192.png (MINIMUM requis)
   public/icons/pwa-512x512.png (RECOMMANDÉ)
   Si pas de générateur d'images disponible → utiliser des SVG renommés en PNG
   ou copier favicon.svg plusieurs fois.

8. OfflineBanner.jsx : import depuis '@hooks/useOfflineStatus' (alias @ configuré).

9. NE PAS activer devOptions.enabled: true en production
   (génère trop de logs et peut causer des problèmes de cache).

VALIDATION :
  cd edugestdz/backend && php artisan test → 724+ ✅
  cd edugestdz/frontend && npm run build → dist/sw.js existe ✅

git push origin develop → CI ✅ → Merger PR
```
