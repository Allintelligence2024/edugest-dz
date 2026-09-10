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
