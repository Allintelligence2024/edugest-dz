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
