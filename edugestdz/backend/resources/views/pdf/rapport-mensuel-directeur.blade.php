<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
  h1   { font-size: 20px; color: #1e40af; margin-bottom: 4px; }
  h2   { font-size: 13px; color: #334155; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; margin-top: 20px; }
  .header  { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .badge   { background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }
  .kpi-row { display: flex; gap: 12px; margin-bottom: 12px; }
  .kpi     { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; text-align: center; }
  .kpi-val { font-size: 18px; font-weight: 900; color: #1e40af; }
  .kpi-lbl { font-size: 9px; color: #64748b; margin-top: 2px; }
  table    { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th       { background: #1e40af; color: #fff; padding: 7px 10px; font-size: 10px; text-align: left; }
  td       { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
  tr:nth-child(even) td { background: #f8fafc; }
  .alert-r { background: #fee2e2; border-left: 3px solid #ef4444; padding: 8px; margin-bottom: 6px; border-radius: 4px; }
  .alert-o { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 8px; margin-bottom: 6px; border-radius: 4px; }
  .footer  { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>

<div class="header">
  <div>
    <h1>EduGest DZ</h1>
    <p style="color:#64748b;font-size:10px;">Rapport mensuel — Direction</p>
    <h2 style="border:none;margin-top:4px;">{{ $periode }}</h2>
  </div>
  <div style="text-align:right;">
    <div class="badge">CONFIDENTIEL</div>
    <p style="font-size:9px;color:#94a3b8;margin-top:6px;">Généré le {{ $genere_le }}</p>
  </div>
</div>

@if(count($dashboard['alertes']) > 0)
<h2>Alertes prioritaires</h2>
@foreach($dashboard['alertes'] as $alerte)
<div class="{{ $alerte['type'] === 'danger' ? 'alert-r' : 'alert-o' }}">
  <strong>{{ $alerte['icone'] }} {{ $alerte['message'] }}</strong>
</div>
@endforeach
@endif

<h2>Indicateurs clés du mois</h2>
<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-val">{{ number_format($dashboard['kpis']['total_eleves']) }}</div>
    <div class="kpi-lbl">Élèves actifs</div>
  </div>
  <div class="kpi">
    <div class="kpi-val">{{ number_format($dashboard['kpis']['ca_mois']) }} DA</div>
    <div class="kpi-lbl">CA encaissé</div>
  </div>
  <div class="kpi">
    <div class="kpi-val">{{ $dashboard['kpis']['taux_recouvrement'] }}%</div>
    <div class="kpi-lbl">Taux recouvrement</div>
  </div>
  <div class="kpi">
    <div class="kpi-val">{{ number_format($dashboard['kpis']['impayes_montant']) }} DA</div>
    <div class="kpi-lbl">Impayés ({{ $dashboard['kpis']['impayes_nb'] }} fact.)</div>
  </div>
</div>

<h2>Évolution CA — 6 derniers mois</h2>
<table>
  <tr>
    @foreach($dashboard['graphiques']['ca_six_mois'] as $m)
    <th style="text-align:center;">{{ $m['mois'] }}</th>
    @endforeach
  </tr>
  <tr>
    @foreach($dashboard['graphiques']['ca_six_mois'] as $m)
    <td style="text-align:center;font-weight:bold;">{{ number_format($m['valeur']) }} DA</td>
    @endforeach
  </tr>
</table>

@if(count($dashboard['graphiques']['top_matieres']) > 0)
<h2>Meilleures moyennes par matière</h2>
<table>
  <thead><tr><th>matière</th><th>Moyenne /20</th></tr></thead>
  <tbody>
    @foreach($dashboard['graphiques']['top_matieres'] as $m)
    <tr><td>{{ $m->matiere }}</td><td>{{ $m->moyenne }}/20</td></tr>
    @endforeach
  </tbody>
</table>
@endif

@if(count($finances['impayes_urgents']) > 0)
<h2>Impayés urgents ({{ count($finances['impayes_urgents']) }} cas)</h2>
<table>
  <thead>
    <tr><th>N° Facture</th><th>Élève</th><th>Montant</th><th>Échéance</th><th>Retard</th></tr>
  </thead>
  <tbody>
    @foreach($finances['impayes_urgents'] as $f)
    <tr>
      <td>{{ $f->numero_facture }}</td>
      <td>{{ $f->eleve_nom }}</td>
      <td>{{ number_format($f->total_ttc) }} DA</td>
      <td>{{ \Carbon\Carbon::parse($f->date_echeance)->format('d/m/Y') }}</td>
      <td style="color:#ef4444;font-weight:bold;">{{ $f->jours_retard }}j</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<div class="footer">
  EduGest DZ — Rapport généré automatiquement · Confidentiel · Réservé à la direction
</div>

</body>
</html>
