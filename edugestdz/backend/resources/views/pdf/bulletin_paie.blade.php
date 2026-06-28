<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Bulletin de Paie - {{ $paie->enseignant->nom }} {{ $paie->enseignant->prenom }}</title>
  <style>
    @page { margin: 12mm; size: A4 portrait; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; line-height: 1.5; }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1E5EBC; padding-bottom: 10px; margin-bottom: 15px; }
    .header h1 { font-size: 14pt; color: #1E5EBC; margin: 0; }
    .header .date { font-size: 8pt; color: #888; }
    .ribbon { text-align: center; font-size: 8pt; color: #555; margin-bottom: 12px; border: 1px dashed #ccc; padding: 4px; }
    .employeur-info, .salarie-info { border: 1px solid #ddd; border-radius: 5px; padding: 8px 12px; margin-bottom: 10px; }
    .employeur-info table, .salarie-info table { width: 100%; border-collapse: collapse; }
    .employeur-info td, .salarie-info td { padding: 2px 6px; font-size: 8pt; }
    .employeur-info td:first-child, .salarie-info td:first-child { font-weight: bold; color: #666; width: 130px; }
    table.details { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.details th { background: #1E5EBC; color: white; padding: 5px 8px; font-size: 8pt; text-align: center; }
    table.details td { padding: 4px 8px; border-bottom: 1px solid #eee; text-align: center; font-size: 8pt; }
    table.details tr:nth-child(even) { background: #f8f9ff; }
    .totaux { margin: 12px 0; }
    .totaux table { width: 100%; border-collapse: collapse; }
    .totaux td { padding: 4px 12px; font-size: 9pt; }
    .totaux .label { font-weight: bold; color: #555; }
    .totaux .value { text-align: left; font-weight: bold; }
    .net-a-payer { background: linear-gradient(135deg, #1E5EBC, #3a7bd5); color: white; text-align: center; padding: 12px; border-radius: 6px; margin: 15px 0; }
    .net-a-payer .amount { font-size: 22pt; font-weight: bold; }
    .net-a-payer .label { font-size: 8pt; opacity: 0.9; }
    .footer { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 7pt; color: #999; text-align: center; }
    .signatures { display: flex; justify-content: space-between; margin-top: 25px; }
    .signatures .sig { text-align: center; width: 40%; }
    .signatures .sig .line { border-top: 1px solid #333; margin-top: 35px; padding-top: 5px; font-size: 8pt; }
  </style>
</head>
<body>
  @php $ens = $paie->enseignant; @endphp
  <div class="header">
    <div>
      <h1>BULLETIN DE PAIE</h1>
      <div>N° BP-{{ $paie->annee }}{{ str_pad($paie->mois, 2, '0', STR_PAD_LEFT) }}-{{ str_pad($ens->id, 3, '0', STR_PAD_LEFT) }}</div>
    </div>
    <div class="date">{{ $moisNom }}<br>Émis le {{ now()->format('d/m/Y') }}</div>
  </div>

  <div class="ribbon">Document à conserver sans limitation de durée</div>

  <div class="employeur-info">
    <table>
      <tr><td>Employeur</td><td>{{ $tenant->nom_etablissement ?? 'Établissement' }}</td></tr>
      <tr><td>NIF</td><td>{{ $tenant->nif ?? '—' }}</td></tr>
      <tr><td>Adresse</td><td>{{ $tenant->adresse ?? '' }}</td></tr>
    </table>
  </div>

  <div class="salarie-info">
    <table>
      <tr><td>Enseignant(e)</td><td>{{ $ens->nom }} {{ $ens->prenom }}</td></tr>
      <tr><td>Matricule</td><td>{{ $ens->matricule ?? '—' }}</td></tr>
      <tr><td>N° CNAS</td><td>{{ $ens->num_cnas ?? '—' }}</td></tr>
      <tr><td>Type contrat</td><td>{{ $ens->type_contrat ?? '—' }}</td></tr>
    </table>
  </div>

  <div class="totaux">
    <table>
      <tr><td class="label">Salaire brut</td><td class="value">{{ number_format($paie->salaire_base, 2) }} DA</td></tr>
      @if($ens->type_contrat === 'vacataire')
      <tr><td class="label">Heures travaillées</td><td class="value">{{ $paie->heures_travaillees }} h</td></tr>
      <tr><td class="label" style="font-size:8pt;color:#888;">Taux horaire</td><td class="value" style="font-size:8pt;color:#888;">{{ number_format($paie->taux_horaire, 2) }} DA/h</td></tr>
      @endif
      <tr><td class="label">CNAS ({{ $detail['taux_cnas'] }})</td><td class="value" style="color:#c0392b;">- {{ number_format($paie->cnas, 2) }} DA</td></tr>
      <tr><td class="label">IRG</td><td class="value" style="color:#c0392b;">- {{ number_format($paie->irg, 2) }} DA</td></tr>
    </table>
  </div>

  <div class="net-a-payer">
    <div class="label">NET À PAYER</div>
    <div class="amount">{{ number_format($paie->salaire_net, 2) }} DA</div>
  </div>

  <div class="signatures">
    <div class="sig">
      <div class="line">Signature de l'enseignant(e)</div>
    </div>
    <div class="sig">
      <div class="line">Signature de l'employeur</div>
    </div>
  </div>

  <div class="footer">
    <p>{{ $tenant->nom_etablissement ?? 'Établissement' }} • NIF {{ $tenant->nif ?? '—' }} • {{ $tenant->telephone ?? '' }}</p>
    <p>Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
  </div>
</body>
</html>
