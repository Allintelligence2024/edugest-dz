<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Résumé Hebdomadaire — EduGest DZ</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:32px;text-align:center;border-radius:16px 16px 0 0;">
            <div style="font-size:36px;margin-bottom:8px;">📊</div>
            <h1 style="color:#ffffff;font-size:22px;font-weight:800;margin:0;letter-spacing:-0.5px;">
              Résumé Hebdomadaire
            </h1>
            <p style="color:#bfdbfe;font-size:13px;margin:8px 0 0;">Semaine du {{ $semaine }}</p>
          </td>
        </tr>

        <!-- Corps -->
        <tr>
          <td style="background:#ffffff;padding:32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">

            <p style="color:#1e293b;font-size:15px;margin:0 0 20px;">Bonjour <strong>{{ $parentPrenom }} {{ $parentNom }}</strong>,</p>

            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px;">
              Voici le résumé de la semaine de <strong style="color:#1e293b;">{{ $elevePrenom }} {{ $eleveNom }}</strong>.
            </p>

            <!-- Notes -->
            @if(count($notes) > 0)
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1px solid #22c55e;border-radius:10px;margin-bottom:20px;">
              <tr>
                <td style="padding:20px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:12px;color:#166534;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:12px;">
                        📝 Notes de la semaine
                      </td>
                    </tr>
                    @foreach($notes as $note)
                    <tr>
                      <td style="font-size:13px;color:#166534;padding:4px 0;">
                        <strong>{{ $note['matiere'] }}</strong> : {{ $note['note'] }}/{{ $note['note_sur'] }}
                      </td>
                    </tr>
                    @endforeach
                  </table>
                </td>
              </tr>
            </table>
            @endif

            <!-- Absences -->
            @if(count($absences) > 0)
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #ef4444;border-radius:10px;margin-bottom:20px;">
              <tr>
                <td style="padding:20px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:12px;color:#991b1b;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:12px;">
                        ⚠️ Absences
                      </td>
                    </tr>
                    @foreach($absences as $absence)
                    <tr>
                      <td style="font-size:13px;color:#991b1b;padding:4px 0;">
                        📅 {{ $absence['date_seance'] }}{{ isset($absence['motif']) && $absence['motif'] ? " — {$absence['motif']}" : '' }}
                      </td>
                    </tr>
                    @endforeach
                  </table>
                </td>
              </tr>
            </table>
            @endif

            <!-- Incidents -->
            @if(count($incidents) > 0)
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;border:1px solid #f97316;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:12px;color:#9a3412;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:12px;">
                        🚨 Incidents
                      </td>
                    </tr>
                    @foreach($incidents as $incident)
                    <tr>
                      <td style="font-size:13px;color:#9a3412;padding:4px 0;">
                        <strong>{{ $incident['type'] }}</strong> ({{ $incident['gravite'] }}) : {{ $incident['description'] }}
                      </td>
                    </tr>
                    @endforeach
                  </table>
                </td>
              </tr>
            </table>
            @endif

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
              {{ $nomEcole }} · {{ $anneeScolaire }}
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
