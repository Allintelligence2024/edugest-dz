<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; padding:16px; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e3a5f; padding-bottom:10px; margin-bottom:14px; }
  .title { font-size:14px; font-weight:bold; color:#1e3a5f; }
  .salle-badge { background:#1e3a5f; color:#fff; padding:8px 14px; border-radius:6px; text-align:center; }
  .salle-badge .salle-nom { font-size:20px; font-weight:900; }
  .meta { font-size:10px; color:#475569; margin-bottom:10px; }
  .pres-table { width:100%; border-collapse:collapse; margin-top:8px; }
  .pres-table th { background:#1e3a5f; color:#fff; padding:7px 8px; font-size:9px; text-align:left; }
  .pres-table td { padding:6px 8px; border:1px solid #e2e8f0; font-size:10px; vertical-align:middle; }
  .pres-table tr:nth-child(even) td { background:#f8fafc; }
  .check-box { width:16px; height:16px; border:1px solid #94a3b8; border-radius:3px; display:inline-block; }
  .surv-section { margin-top:14px; background:#f0fdf4; border:1px solid #16a34a; border-radius:6px; padding:10px; }
  .surv-title { font-size:10px; font-weight:bold; color:#15803d; margin-bottom:6px; }
  .surv-row { display:flex; justify-content:space-between; margin-bottom:4px; font-size:10px; border-bottom:1px dashed #dcfce7; padding-bottom:4px; }
  .sig-area { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:14px; }
  .sig-box { border:1px dashed #cbd5e1; border-radius:4px; height:50px; display:flex; align-items:flex-end; justify-content:center; padding-bottom:4px; font-size:9px; color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="title">📋 FEUILLE DE PRÉSENCE</div>
    <div class="meta">
      Examen : {{ $session->type }} {{ $session->annee_scolaire }}<br>
      Centre : {{ $session->nom_centre ?? '—' }} — {{ $session->wilaya ?? '' }}<br>
      Épreuve : {{ request('matiere', 'Toutes les épreuves') }}
    </div>
  </div>
  <div class="salle-badge">
    <div style="font-size:9px;opacity:.8">SALLE</div>
    <div class="salle-nom">{{ $salle->nom }}</div>
    <div style="font-size:9px;opacity:.8">{{ $salle->nb_candidats_affectes }} candidats</div>
  </div>
</div>

<table class="pres-table">
  <thead>
    <tr>
      <th style="width:30px">N°</th>
      <th style="width:40px">Place</th>
      <th>Nom et Prénom</th>
      <th style="width:60px">N° Inscr.</th>
      <th style="width:50px">Type</th>
      <th style="width:40px;text-align:center">Présent</th>
      <th style="width:60px">Signature</th>
    </tr>
  </thead>
  <tbody>
    @foreach($candidats as $i => $c)
    <tr>
      <td style="text-align:center;color:#94a3b8">{{ $i+1 }}</td>
      <td style="text-align:center;font-weight:bold;color:#1d4ed8">{{ $c->rangee }}{{ $c->colonne }}</td>
      <td><strong>{{ $c->nom }}</strong> {{ $c->prenom }}</td>
      <td style="font-size:9px;color:#475569">{{ $c->numero_inscription ?? '—' }}</td>
      <td style="font-size:9px;text-align:center">{{ $c->type_candidat === 'libre' ? 'Libre' : 'Scol.' }}</td>
      <td style="text-align:center"><div class="check-box"></div></td>
      <td style="border-right:none"></td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="surv-section">
  <div class="surv-title">👨‍🏫 Surveillants affectés à cette salle :</div>
  @foreach($surveillants as $s)
  <div class="surv-row">
    <span><strong>{{ $s->nom }} {{ $s->prenom }}</strong></span>
    <span style="color:#475569">{{ $s->specialite ?? '—' }}</span>
    <span style="color:#94a3b8">{{ \App\Models\SurveiillantExamen::ROLES[$s->role] ?? $s->role }}</span>
  </div>
  @endforeach
</div>

<div style="margin-top:12px;font-size:9px;color:#475569;">
  Présents : _____ / {{ $candidats->count() }} &nbsp;&nbsp; Absents : _____ &nbsp;&nbsp; Incidents : □ Oui □ Non
</div>

<div class="sig-area">
  <div class="sig-box">Surveillant 1</div>
  <div class="sig-box">Surveillant 2</div>
  <div class="sig-box">Chef de Centre</div>
</div>

</body>
</html>
