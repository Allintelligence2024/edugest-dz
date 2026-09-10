<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 16px 24px; }

  .top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e40af; padding-bottom: 10px; margin-bottom: 12px; }
  .etab h3 { font-size: 13px; color: #1e40af; margin-bottom: 2px; }
  .etab p  { font-size: 9px; color: #555; }
  .type-badge {
    padding: 6px 16px; border-radius: 4px; font-size: 12px; font-weight: bold;
    text-align: center;
  }

  @php
    $colors = [
      'retard'               => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e'],
      'sortie_autorisee'     => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'text' => '#1e3a8a'],
      'convocation'          => ['bg' => '#fee2e2', 'border' => '#ef4444', 'text' => '#7f1d1d'],
      'entree_exceptionnelle'=> ['bg' => '#d1fae5', 'border' => '#10b981', 'text' => '#064e3b'],
    ];
    $c = $colors[$billet->type] ?? ['bg' => '#f3f4f6', 'border' => '#6b7280', 'text' => '#111827'];
  @endphp

  .type-badge { background: {{ $c['bg'] }}; border: 2px solid {{ $c['border'] }}; color: {{ $c['text'] }}; }

  .grid2 { display: flex; gap: 20px; margin-bottom: 10px; }
  .col { flex: 1; }
  .field { margin-bottom: 7px; }
  .field label { font-size: 9px; color: #6b7280; text-transform: uppercase; display: block; }
  .field span  { font-size: 12px; font-weight: bold; }

  .motif-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 12px; margin-bottom: 10px; min-height: 36px; }
  .motif-box label { font-size: 9px; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 4px; }

  .sigs { display: flex; justify-content: space-between; margin-top: 12px; }
  .sig  { text-align: center; width: 30%; }
  .sig-line { border-top: 1px solid #333; margin-top: 30px; padding-top: 4px; font-size: 9px; color: #555; }

  .footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 6px; }

  .numero { font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="top">
  <div class="etab">
    <h3>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h3>
    <p>{{ $tenant->adresse ?? '' }} | Tél : {{ $tenant->telephone ?? '—' }}</p>
  </div>
  <div style="text-align:center;">
    <div class="type-badge">{{ strtoupper($billet->type_label) }}</div>
    <p class="numero" style="margin-top:4px;">N° {{ strtoupper(substr($billet->id, 0, 8)) }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:9px;color:#555;">Date : {{ $billet->date_billet->format('d/m/Y') }}</p>
    @if($billet->heure)
    <p style="font-size:9px;color:#555;">Heure : {{ \Carbon\Carbon::createFromTimeString($billet->heure)->format('H:i') }}</p>
    @endif
  </div>
</div>

<div class="grid2">
  <div class="col">
    <div class="field">
      <label>Nom & Prénom</label>
      <span>{{ strtoupper($eleve->nom) }} {{ ucfirst($eleve->prenom) }}</span>
    </div>
    <div class="field">
      <label>Niveau</label>
      <span>{{ $eleve->niveau_scolaire ?? '—' }}</span>
    </div>
  </div>
  <div class="col">
    <div class="field">
      <label>N° Inscription</label>
      <span>{{ $eleve->numero_inscription ?? '—' }}</span>
    </div>
    <div class="field">
      <label>Parent prévenu</label>
      <span>{{ $billet->parent_prevenu ? '✓ Oui' : '✗ Non' }}</span>
    </div>
  </div>
</div>

<div class="motif-box">
  <label>
    @if($billet->type === 'retard') Motif du retard
    @elseif($billet->type === 'sortie_autorisee') Motif de la sortie
    @elseif($billet->type === 'convocation') Objet de la convocation
    @else Motif
    @endif
  </label>
  {{ $billet->motif ?? 'Non précisé' }}
</div>

@if($billet->note)
<div class="motif-box" style="font-size:10px;color:#555;">
  <label>Observations</label>
  {{ $billet->note }}
</div>
@endif

<div class="sigs">
  <div class="sig">
    <div class="sig-line">Signature Direction</div>
  </div>
  <div class="sig">
    <div class="sig-line">Signature Parent / Tuteur</div>
  </div>
  <div class="sig">
    <div class="sig-line">Signature Élève</div>
  </div>
</div>

<div class="footer">
  EduGest DZ — {{ $tenant->nom_etablissement ?? '' }} — Billet généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
