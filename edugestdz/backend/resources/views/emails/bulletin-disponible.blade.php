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
