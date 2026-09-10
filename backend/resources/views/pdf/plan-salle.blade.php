<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1e293b; padding:20px; }
  .header { text-align:center; border-bottom:2px solid #1e3a5f; padding-bottom:10px; margin-bottom:16px; }
  .title { font-size:16px; font-weight:bold; color:#1e3a5f; }
  .salle-info { font-size:11px; color:#475569; margin-top:4px; }
  .grid-container { display:flex; flex-direction:column; gap:6px; margin-top:16px; }
  .row { display:flex; gap:6px; justify-content:center; }
  .seat { width:52px; height:52px; border:1px solid #cbd5e1; border-radius:4px; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f8fafc; font-size:8px; }
  .seat-occupied { background:#dbeafe; border-color:#2563eb; }
  .seat-empty { background:#f8fafc; border-style:dashed; color:#94a3b8; }
  .seat-number { font-weight:bold; font-size:9px; color:#1d4ed8; }
  .seat-name { font-size:7px; color:#475569; max-width:48px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .legend { display:flex; gap:20px; justify-content:center; margin-top:20px; font-size:9px; }
  .legend-item { display:flex; align-items:center; gap:6px; }
  .legend-box { width:16px; height:16px; border-radius:3px; }
  .footer { text-align:center; margin-top:20px; font-size:8px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
  .tableau { position:relative; width:80%; margin:20px auto 10px; border:2px solid #1e3a5f; border-radius:6px; background:#e2e8f0; text-align:center; padding:8px; font-weight:bold; font-size:11px; color:#1e3a5f; }
</style>
</head>
<body>

<div class="header">
  <div class="title">🏫 PLAN DE SALLE</div>
  <div class="salle-info">
    {{ $salle->nom }} — Capacité : {{ $salle->capacite_totale }} places — {{ $salle->nb_candidats_affectes }} candidats
  </div>
  <div class="salle-info" style="font-size:10px;">
    {{ $session->nom_centre ?? '—' }} · {{ $session->type }} {{ $session->annee_scolaire }}
  </div>
</div>

<div class="tableau">TABLEAU</div>

<div class="grid-container">
  @for($r = 1; $r <= $nbRangees; $r++)
    <div class="row">
      @for($c = 1; $c <= $nbCol; $c++)
        @php
          $key = chr(64 + $r) . $c;
          $candidat = $candidats[$key] ?? null;
        @endphp
        @if($candidat)
          <div class="seat seat-occupied">
            <div class="seat-number">{{ $key }}</div>
            <div class="seat-name">{{ $candidat->nom }} {{ mb_substr($candidat->prenom, 0, 8) }}</div>
          </div>
        @else
          <div class="seat seat-empty">
            <div style="color:#cbd5e1;font-size:9px;">{{ chr(64 + $r) }}{{ $c }}</div>
            <div style="font-size:7px;">libre</div>
          </div>
        @endif
      @endfor
    </div>
  @endfor
</div>

<div class="legend">
  <div class="legend-item">
    <div class="legend-box" style="background:#dbeafe;border:1px solid #2563eb;"></div>
    <span>Occupé</span>
  </div>
  <div class="legend-item">
    <div class="legend-box" style="background:#f8fafc;border:1px dashed #cbd5e1;"></div>
    <span>Libre</span>
  </div>
</div>

<div class="footer">
  EduGest DZ — {{ $session->nom_centre ?? '' }} · Document généré le {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
