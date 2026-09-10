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
