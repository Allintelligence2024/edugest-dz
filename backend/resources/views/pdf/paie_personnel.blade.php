<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
  .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
  .logo-zone h2 { color: #1e40af; font-size: 16px; margin: 0; }
  .logo-zone p  { margin: 2px 0; font-size: 10px; color: #555; }
  .titre { text-align: center; background: #1e40af; color: #fff; padding: 10px; font-size: 14px; font-weight: bold; margin-bottom: 16px; border-radius: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { background: #e8f0fe; color: #1e40af; text-align: left; padding: 7px 10px; font-size: 10px; text-transform: uppercase; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
  .net-box { background: #1e40af; color: #fff; text-align: center; padding: 16px; border-radius: 6px; margin: 16px 0; }
  .net-box .montant { font-size: 24px; font-weight: bold; }
  .net-box .label   { font-size: 11px; margin-bottom: 4px; opacity: 0.85; }
  .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig-box { text-align: center; width: 45%; }
  .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; font-size: 10px; color: #555; }
  .mention { font-size: 9px; color: #888; text-align: center; margin-top: 20px; }
  .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 20px; font-size: 10px; }
</style>
</head>
<body>

<div class="header">
  <div class="logo-zone">
    <h2>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h2>
    <p>{{ $tenant->adresse ?? '' }}</p>
    <p>NIF : {{ $tenant->nif ?? '—' }} | NIS : {{ $tenant->nis ?? '—' }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:10px;color:#555;">N° Fiche : PAIE-{{ strtoupper($agent->matricule ?? 'XXX') }}-{{ $paie->mois }}-{{ $paie->annee }}</p>
    <p style="font-size:10px;color:#555;">Émise le : {{ now()->format('d/m/Y') }}</p>
  </div>
</div>

<div class="titre">FICHE DE PAIE — {{ strtoupper($moisNom) }}</div>

<table>
  <tr><th colspan="4">Informations Agent</th></tr>
  <tr>
    <td><strong>Nom & Prénom</strong></td>
    <td>{{ $agent->nom_complet }}</td>
    <td><strong>Poste</strong></td>
    <td>{{ $agent->poste_affiche }} <span class="badge">{{ $agent->type_contrat }}</span></td>
  </tr>
  <tr>
    <td><strong>Matricule</strong></td>
    <td>{{ $agent->matricule ?? '—' }}</td>
    <td><strong>N° CNAS</strong></td>
    <td>{{ $agent->num_cnas ?? 'Non affilié' }}</td>
  </tr>
  <tr>
    <td><strong>Date embauche</strong></td>
    <td>{{ $agent->date_embauche?->format('d/m/Y') ?? '—' }}</td>
    <td><strong>Ancienneté</strong></td>
    <td>{{ $agent->anciennete_ans }} an(s)</td>
  </tr>
</table>

<table>
  <tr><th colspan="4">Présence & Activité</th></tr>
  <tr>
    <td><strong>Jours ouvrables</strong></td>
    <td>{{ $paie->jours_ouvrables }} jours</td>
    <td><strong>Jours travaillés</strong></td>
    <td>{{ $paie->jours_travailles }} jours</td>
  </tr>
  @if($paie->retenues_absences > 0)
  <tr>
    <td><strong>Absences injustifiées</strong></td>
    <td colspan="3" style="color:#dc2626;">− {{ number_format($paie->retenues_absences, 2) }} DA</td>
  </tr>
  @endif
</table>

<table>
  <tr><th colspan="2">Détail du Salaire</th></tr>
  <tr><td>Salaire brut</td><td style="text-align:right;"><strong>{{ number_format($paie->salaire_base, 2) }} DA</strong></td></tr>
  @if($paie->cnas > 0)
  <tr><td>Cotisation CNAS salariale ({{ $detail['taux_cnas'] }})</td><td style="text-align:right;color:#dc2626;">− {{ number_format($paie->cnas, 2) }} DA</td></tr>
  @endif
  <tr><td>Base imposable IRG</td><td style="text-align:right;">{{ number_format($detail['base_imposable'], 2) }} DA</td></tr>
  <tr><td>Impôt sur le Revenu Global (IRG)</td><td style="text-align:right;color:#dc2626;">− {{ number_format($paie->irg, 2) }} DA</td></tr>
</table>

<div class="net-box">
  <div class="label">NET À PAYER</div>
  <div class="montant">{{ number_format($paie->salaire_net, 2) }} DA</div>
  <div style="font-size:10px;margin-top:4px;opacity:0.8;">SMIG mensuel Algérie : {{ $detail['smig'] }}</div>
</div>

<div class="signatures">
  <div class="sig-box">
    <div class="sig-line">Signature de l'agent</div>
    <p style="font-size:10px;margin-top:6px;">{{ $agent->nom_complet }}</p>
  </div>
  <div class="sig-box">
    <div class="sig-line">Cachet & Signature employeur</div>
    <p style="font-size:10px;margin-top:6px;">{{ $tenant->nom_etablissement ?? '' }}</p>
  </div>
</div>

<p class="mention">Document à conserver sans limitation de durée · EduGest DZ</p>
</body>
</html>
