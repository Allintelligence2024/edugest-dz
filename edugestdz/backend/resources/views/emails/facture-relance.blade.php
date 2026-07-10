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
