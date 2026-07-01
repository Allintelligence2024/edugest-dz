<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; padding: 16px; }
  h1   { color: #1e40af; font-size: 16px; text-align: center; margin-bottom: 4px; }
  .sub { text-align: center; color: #555; font-size: 10px; margin-bottom: 16px; }
  .kpi-row { display: flex; gap: 10px; margin-bottom: 16px; }
  .kpi { flex: 1; background: #e8f0fe; border-radius: 4px; padding: 10px; text-align: center; }
  .kpi .val { font-size: 20px; font-weight: bold; color: #1e40af; }
  .kpi .lbl { font-size: 9px; color: #555; }
  h2   { color: #1e40af; font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
  th { background: #1e40af; color: #fff; padding: 5px 8px; text-align: left; }
  td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
  tr:nth-child(even) td { background: #f8fafc; }
  .alerte { color: #dc2626; font-weight: bold; }
  .footer { text-align: center; margin-top: 20px; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>

<h1>RAPPORT D'INVENTAIRE {{ $annee }}</h1>
<div class="sub">{{ $tenant->nom_etablissement ?? '' }} · Édité le {{ $date_rapport }}</div>

<div class="kpi-row">
  <div class="kpi"><div class="val">{{ $nb_total }}</div><div class="lbl">Articles recensés</div></div>
  <div class="kpi"><div class="val">{{ number_format($valeur_totale, 0, ',', ' ') }} DA</div><div class="lbl">Valeur totale estimée</div></div>
  <div class="kpi"><div class="val" style="color:{{ $nb_alertes > 0 ? '#dc2626' : '#16a34a' }}">{{ $nb_alertes }}</div><div class="lbl">Articles en alerte stock</div></div>
</div>

@foreach($par_categorie as $categorie => $data)
<h2>{{ \App\Models\ArticleStock::make(['categorie' => $categorie])->categorie_label }} ({{ $data['nb_articles'] }} articles)</h2>
<table>
  <tr>
    <th>Réf.</th>
    <th>Désignation</th>
    <th>Localisation</th>
    <th>État</th>
    <th style="text-align:center">Qté</th>
    <th style="text-align:center">Min.</th>
    <th style="text-align:right">Val. Unit.</th>
    <th style="text-align:right">Val. Totale</th>
  </tr>
  @foreach($data['articles'] as $art)
  <tr>
    <td>{{ $art->reference ?? '—' }}</td>
    <td>{{ $art->nom }}@if($art->numero_serie) <br><span style="color:#888;font-size:8px;">S/N: {{ $art->numero_serie }}</span>@endif</td>
    <td>{{ $art->localisation ?? '—' }}</td>
    <td class="{{ $art->etat === 'hors_service' ? 'alerte' : '' }}">{{ $art->etat_label }}</td>
    <td style="text-align:center" class="{{ $art->en_alerte ? 'alerte' : '' }}">{{ $art->quantite_stock }}</td>
    <td style="text-align:center">{{ $art->quantite_minimum }}</td>
    <td style="text-align:right">{{ $art->valeur_unitaire ? number_format($art->valeur_unitaire, 2) . ' DA' : '—' }}</td>
    <td style="text-align:right">{{ number_format($art->valeur_totale, 2) }} DA</td>
  </tr>
  @endforeach
  <tr style="background:#e8f0fe;font-weight:bold;">
    <td colspan="6">Sous-total {{ \App\Models\ArticleStock::make(['categorie' => $categorie])->categorie_label }}</td>
    <td></td>
    <td style="text-align:right">{{ number_format($data['valeur_totale'], 2) }} DA</td>
  </tr>
</table>
@endforeach

<div class="footer">
  EduGest DZ · Rapport inventaire {{ $annee }} · {{ $tenant->nom_etablissement ?? '' }}
</div>
</body>
</html>
