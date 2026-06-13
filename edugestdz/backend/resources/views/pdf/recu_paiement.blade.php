{{-- resources/views/pdf/recu_paiement.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#2C3E50; padding:30px; }
    .header { display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid #1E5EBC; padding-bottom:15px; margin-bottom:20px; }
    .header h1 { font-size:20px; color:#1E5EBC; }
    .recu-num { font-size:14px; color:#6C757D; }
    .grid { display:flex; gap:20px; margin:15px 0; }
    .box { flex:1; border:1px solid #DEE2E6; border-radius:8px; padding:12px; }
    .box h3 { color:#1E5EBC; font-size:10px; text-transform:uppercase; margin-bottom:5px; }
    .montant-principal { font-size:28px; font-weight:bold; color:#27AE60; text-align:center; padding:20px; background:#F8FAFC; border-radius:8px; margin:15px 0; }
    table { width:100%; border-collapse:collapse; margin:10px 0; }
    td { padding:6px 8px; border-bottom:1px solid #F0F4F8; }
    td:first-child { color:#6C757D; font-weight:600; width:140px; }
    .footer { margin-top:20px; padding-top:15px; border-top:1px solid #DEE2E6; text-align:center; font-size:9px; color:#ADB5BD; }
  </style>
</head>
<body>
<div class="header">
  <div>
    <h1>{{ $tenant->nom_etablissement }}</h1>
    <p style="color:#6C757D; font-size:10px;">{{ $tenant->adresse }}</p>
  </div>
  <div class="recu-num">
    <strong>Reçu de paiement</strong><br>
    N° {{ $paiement->id }}
  </div>
</div>
<div class="montant-principal">
  {{ number_format($paiement->montant, 2, ',', ' ') }} DA
</div>
<div class="grid">
  <div class="box">
    <h3>Élève</h3>
    <p><strong>{{ $paiement->facture->eleve->nom }} {{ $paiement->facture->eleve->prenom }}</strong><br>{{ $paiement->facture->eleve->numero_inscription }}</p>
  </div>
  <div class="box">
    <h3>Paiement</h3>
    <p><strong>{{ ucfirst($paiement->mode_paiement) }}</strong><br>{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</p>
  </div>
</div>
<table>
  <tr><td>Facture</td><td>{{ $paiement->facture->numero_facture }}</td></tr>
  <tr><td>Montant payé</td><td><strong>{{ number_format($paiement->montant, 2, ',', ' ') }} DA</strong></td></tr>
  <tr><td>Mode de paiement</td><td>{{ ucfirst($paiement->mode_paiement) }}</td></tr>
  <tr><td>Date</td><td>{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</td></tr>
  @if($paiement->notes)<tr><td>Notes</td><td>{{ $paiement->notes }}</td></tr>@endif
  <tr><td>Encaissé par</td><td>{{ $paiement->recu_par }}</td></tr>
</table>
<div class="footer">
  <p>{{ $tenant->nom_etablissement }} — {{ $tenant->telephone }} — {{ $tenant->email }}</p>
  <p>Reçu généré le {{ now()->format('d/m/Y à H:i') }} — EduGest DZ</p>
</div>
</body>
</html>
