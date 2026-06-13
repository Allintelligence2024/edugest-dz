{{-- resources/views/pdf/facture.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#2C3E50; background:#fff; }
    .header { background:#1E5EBC; color:#fff; padding:20px 30px; display:flex; justify-content:space-between; align-items:center; }
    .header h1 { font-size:22px; font-weight:bold; }
    .header .meta { text-align:right; font-size:11px; opacity:0.9; }
    .header .num  { font-size:18px; font-weight:bold; }
    .body { padding:25px 30px; }
    .parties { display:flex; justify-content:space-between; margin-bottom:20px; }
    .box     { background:#F8FAFC; border:1px solid #DEE2E6; border-radius:8px; padding:12px; width:48%; }
    .box h3  { color:#1E5EBC; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
    .box p   { font-size:11px; line-height:1.6; color:#495057; }
    table        { width:100%; border-collapse:collapse; margin:15px 0; }
    thead th     { background:#1E5EBC; color:#fff; padding:9px 12px; text-align:left; font-size:11px; }
    tbody td     { padding:9px 12px; border-bottom:1px solid #F0F4F8; font-size:11px; }
    tbody tr:nth-child(even) { background:#F8FAFC; }
    .text-right  { text-align:right; }
    .text-center { text-align:center; }
    .totals      { margin-left:auto; width:260px; margin-top:5px; }
    .totals table th, .totals table td { padding:6px 10px; font-size:11px; }
    .totals .total-row td { background:#1E5EBC; color:#fff; font-weight:bold; font-size:13px; }
    .badge-paye    { background:#EDFAF3; color:#27AE60; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:bold; }
    .badge-impaye  { background:#FDECEA; color:#E74C3C; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:bold; }
    .badge-partiel { background:#FFF8EC; color:#F39C12; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:bold; }
    .footer { margin-top:25px; padding:12px 30px; background:#F8FAFC; border-top:2px solid #DEE2E6; text-align:center; font-size:10px; color:#6C757D; }
  </style>
</head>
<body>
<div class="header">
  <div>
    <h1>{{ $tenant->nom_etablissement }}</h1>
    <p style="opacity:0.85; font-size:11px;">{{ $tenant->adresse }}</p>
    <p style="opacity:0.85; font-size:11px;">Tél: {{ $tenant->telephone }} | {{ $tenant->email }}</p>
    @if($tenant->nif) <p style="opacity:0.7; font-size:10px;">NIF: {{ $tenant->nif }}</p> @endif
  </div>
  <div class="meta">
    <div style="margin-bottom:6px;">
      @if($facture->statut === 'payée')
        <span class="badge-paye">✓ PAYÉE</span>
      @elseif(in_array($facture->statut, ['émise','en_retard']))
        <span class="badge-impaye">⚡ À PAYER</span>
      @else
        <span class="badge-partiel">⏳ PARTIELLE</span>
      @endif
    </div>
    <div class="num">{{ $facture->numero_facture }}</div>
    <p>Émise le {{ \Carbon\Carbon::parse($facture->date_emission)->format('d/m/Y') }}</p>
    <p>Échéance : <strong>{{ \Carbon\Carbon::parse($facture->date_echeance)->format('d/m/Y') }}</strong></p>
  </div>
</div>
<div class="body">
  <div class="parties">
    <div class="box">
      <h3>📤 Centre</h3>
      <p><strong>{{ $tenant->nom_etablissement }}</strong><br>{{ $tenant->adresse }}<br>{{ $tenant->telephone }}<br>{{ $tenant->email }}</p>
    </div>
    <div class="box">
      <h3>📥 Élève</h3>
      @php $eleve = $facture->eleve; $parent = $eleve->parents->firstWhere('pivot.est_principal', true); @endphp
      <p><strong>{{ strtoupper($eleve->nom) }} {{ $eleve->prenom }}</strong><br>N° {{ $eleve->numero_inscription }}<br>Niveau : {{ $eleve->niveau_scolaire }}<br>@if($parent) Contact : {{ $parent->telephone_1 }} @endif</p>
    </div>
  </div>
  <table>
    <thead><tr><th>Description</th><th class="text-center">Qté</th><th class="text-right">Prix unitaire</th><th class="text-right">Total</th></tr></thead>
    <tbody>
      @foreach($facture->lignes as $ligne)
      <tr>
        <td>{{ $ligne->description }}</td>
        <td class="text-center">{{ $ligne->quantite }}</td>
        <td class="text-right">{{ number_format($ligne->prix_unitaire, 2, ',', ' ') }} DA</td>
        <td class="text-right"><strong>{{ number_format($ligne->total, 2, ',', ' ') }} DA</strong></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div class="totals">
    <table>
      <tr><th>Sous-total</th><td class="text-right">{{ number_format($facture->sous_total, 2, ',', ' ') }} DA</td></tr>
      @if($facture->remise_montant > 0)
      <tr><th>Remise {{ $facture->remise_pct > 0 ? "({$facture->remise_pct}%)" : '' }}</th><td class="text-right" style="color:#27AE60;">- {{ number_format($facture->remise_montant, 2, ',', ' ') }} DA</td></tr>
      @endif
      <tr class="total-row"><td><strong>💰 TOTAL TTC</strong></td><td class="text-right"><strong>{{ number_format($facture->total_ttc, 2, ',', ' ') }} DA</strong></td></tr>
    </table>
  </div>
  @if($facture->paiements->count() > 0)
  <div style="margin-top:20px;">
    <h3 style="color:#1E5EBC; font-size:11px; margin-bottom:8px; text-transform:uppercase;">✅ Historique des paiements</h3>
    <table>
      <thead><tr><th>Date</th><th>Montant</th><th>Mode</th></tr></thead>
      <tbody>
        @foreach($facture->paiements->where('statut','confirmé') as $p)
        <tr><td>{{ \Carbon\Carbon::parse($p->date_paiement)->format('d/m/Y') }}</td><td><strong>{{ number_format($p->montant, 2, ',', ' ') }} DA</strong></td><td>{{ ucfirst($p->mode_paiement) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
  @if($facture->notes)
  <div style="margin-top:15px; background:#FFF8EC; border-radius:8px; padding:10px;"><strong>Notes :</strong> {{ $facture->notes }}</div>
  @endif
</div>
<div class="footer">
  <p>{{ $tenant->nom_etablissement }} — {{ $tenant->adresse }} — {{ $tenant->telephone }}</p>
  <p style="margin-top:3px; color:#ADB5BD;">Document généré le {{ now()->format('d/m/Y à H:i') }} — EduGest DZ v2.0</p>
</div>
</body>
</html>
