<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 20px; color: #111; }
  .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
  .etab h2 { font-size: 15px; color: #1e40af; margin: 0; }
  .etab p  { font-size: 10px; color: #555; margin: 2px 0; }
  .titre   { text-align: center; background: #1e40af; color: #fff; padding: 10px; font-size: 14px; font-weight: bold; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { background: #e8f0fe; color: #1e40af; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
  .total-box { text-align: right; font-size: 14px; font-weight: bold; padding: 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
  .sigs { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig  { text-align: center; width: 40%; border-top: 1px solid #333; padding-top: 6px; font-size: 10px; }
</style>
</head>
<body>
<div class="header">
  <div class="etab">
    <h2>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h2>
    <p>{{ $tenant->adresse ?? '' }}</p>
    <p>NIF : {{ $tenant->nif ?? '—' }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:11px;font-weight:bold;">BON DE COMMANDE N° {{ $bon->numero }}</p>
    <p style="font-size:10px;color:#555;">Date : {{ $bon->date_commande->format('d/m/Y') }}</p>
    @if($bon->date_livraison_prevue)
    <p style="font-size:10px;color:#555;">Livraison prévue : {{ $bon->date_livraison_prevue->format('d/m/Y') }}</p>
    @endif
  </div>
</div>

<div class="titre">BON DE COMMANDE</div>

<table>
  <tr><th colspan="2">Fournisseur</th></tr>
  <tr><td><strong>{{ $bon->fournisseur }}</strong></td><td>{{ $bon->fournisseur_contact ?? '' }}</td></tr>
</table>

<table>
  <tr>
    <th>#</th>
    <th>Désignation</th>
    <th style="text-align:center">Qté</th>
    <th style="text-align:right">P.U. (DA)</th>
    <th style="text-align:right">Total (DA)</th>
  </tr>
  @foreach($bon->lignes as $i => $ligne)
  <tr>
    <td>{{ $i + 1 }}</td>
    <td>{{ $ligne->designation }}</td>
    <td style="text-align:center">{{ $ligne->quantite }}</td>
    <td style="text-align:right">{{ number_format($ligne->prix_unitaire, 2) }}</td>
    <td style="text-align:right"><strong>{{ number_format($ligne->total, 2) }}</strong></td>
  </tr>
  @endforeach
</table>

<div class="total-box">
  MONTANT TOTAL TTC : {{ number_format($bon->montant_total, 2) }} DA
</div>

@if($bon->note)
<p style="margin-top:12px;font-size:10px;color:#555;"><strong>Note :</strong> {{ $bon->note }}</p>
@endif

<div class="sigs">
  <div class="sig">Responsable des achats</div>
  <div class="sig">Fournisseur / Cachet</div>
</div>
</body>
</html>
