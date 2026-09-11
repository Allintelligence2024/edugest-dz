<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Bulletin de Notes - {{ $eleve->nom }} {{ $eleve->prenom }}</title>
  <style>
    @page { margin: 15mm; size: A4 portrait; }
    body { font-family: 'DejaVu Sans', 'Noto Naskh Arabic', sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.6; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #1E5EBC; padding-bottom: 15px; }
    .header h1 { font-size: 18pt; color: #1E5EBC; margin: 0 0 5px; }
    .header h2 { font-size: 13pt; color: #555; margin: 0; font-weight: normal; }
    .header .annee { font-size: 9pt; color: #888; margin-top: 3px; }
    .info-box { border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-bottom: 20px; }
    .info-box table { width: 100%; border-collapse: collapse; }
    .info-box td { padding: 3px 8px; font-size: 9pt; }
    .info-box td:first-child { font-weight: bold; color: #666; white-space: nowrap; width: 140px; }
    table.notes { width: 100%; border-collapse: collapse; margin: 15px 0; }
    table.notes th { background: #1E5EBC; color: white; padding: 7px 10px; font-size: 9pt; text-align: center; }
    table.notes td { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: center; font-size: 9pt; }
    table.notes tr:nth-child(even) { background: #f8f9ff; }
    .presences { display: flex; gap: 10px; justify-content: center; margin: 15px 0; }
    .presences .stat { padding: 8px 15px; border-radius: 8px; text-align: center; }
    .presences .stat .count { font-size: 16pt; font-weight: bold; }
    .presences .stat .label { font-size: 8pt; }
    .moyenne-box { text-align: center; padding: 12px; margin: 15px 0; border-radius: 8px; }
    .moyenne-box.generale { background: linear-gradient(135deg, #1E5EBC, #3a7bd5); color: white; }
    .moyenne-box .value { font-size: 28pt; font-weight: bold; }
    .moyenne-box .label { font-size: 9pt; opacity: 0.9; }
    .appreciation { margin-top: 15px; padding: 10px; background: #f0f4ff; border-left: 4px solid #1E5EBC; border-radius: 4px; font-size: 9pt; }
    .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; font-size: 8pt; color: #999; text-align: center; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8pt; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-danger { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <div class="header">
    <h1>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h1>
    <h2>BULLETIN DE NOTES</h2>
    <div class="annee">Année scolaire {{ $bulletin->annee_scolaire ?? now()->year . '/' . (now()->year+1) }} — Trimestre {{ $bulletin->trimestre }}</div>
  </div>

  <div class="info-box">
    <table>
      <tr><td>Élève</td><td>{{ $eleve->nom }} {{ $eleve->prenom }}</td></tr>
      <tr><td>Date naissance</td><td>{{ $eleve->date_naissance?->format('d/m/Y') ?? $eleve->date_naissance }}</td></tr>
      <tr><td>Niveau</td><td>{{ $eleve->niveau_scolaire }}</td></tr>
      <tr><td>N° Inscription</td><td>{{ $eleve->numero_inscription }}</td></tr>
      <tr><td>Groupe</td><td>{{ $groupe->nom ?? '—' }} — {{ $groupe->matiere->nom_fr ?? '—' }}</td></tr>
    </table>
  </div>

  @if(count($notes) > 0)
    @foreach($notes as $typeEval => $notesType)
    <h3 style="font-size:10pt; color:#1E5EBC; margin-top:15px;">{{ ucfirst($typeEval) }}</h3>
    <table class="notes">
      <thead>
        <tr>
          <th style="text-align:left;">Matière</th>
          <th style="width:12%">Note</th>
          <th style="width:12%">Coeff.</th>
          <th style="width:12%">Moy.</th>
          <th style="width:14%">Appréciation</th>
        </tr>
      </thead>
      <tbody>
        @foreach($notesType as $note)
        <tr>
          <td style="text-align:left;">{{ $note->evaluation->titre ?? 'Sans titre' }}</td>
          <td><strong>{{ number_format($note->note, 2) }}/20</strong></td>
          <td>{{ $note->evaluation->coefficient ?? 1 }}</td>
          <td><strong>{{ number_format($note->note * ($note->evaluation->coefficient ?? 1), 2) }}</strong></td>
          <td>
            @php $m = $note->note; @endphp
            <span class="badge {{ $m >= 10 ? 'badge-success' : 'badge-danger' }}">
              {{ $m >= 16 ? 'TB' : ($m >= 14 ? 'B' : ($m >= 12 ? 'AB' : ($m >= 10 ? 'P' : 'I'))) }}
            </span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endforeach
  @else
    <p style="text-align:center;color:#999;font-style:italic;margin:20px 0;">Aucune note enregistrée pour ce trimestre</p>
  @endif

  @if(count($presenceStats) > 0)
  <h3 style="font-size:10pt; color:#1E5EBC; margin-top:15px;">Présences</h3>
  <div class="presences">
    <div class="stat" style="background:#d4edda;">
      <div class="count">{{ $presenceStats['présent'] ?? 0 }}</div>
      <div class="label">Présences</div>
    </div>
    <div class="stat" style="background:#fff3cd;">
      <div class="count">{{ $presenceStats['retard'] ?? 0 }}</div>
      <div class="label">Retards</div>
    </div>
    <div class="stat" style="background:#f8d7da;">
      <div class="count">{{ $presenceStats['absent'] ?? 0 }}</div>
      <div class="label">Absences</div>
    </div>
    <div class="stat" style="background:#e2e3e5;">
      <div class="count">{{ $presenceStats['justifié'] ?? 0 }}</div>
      <div class="label">Justifiées</div>
    </div>
  </div>
  @endif

  <div class="moyenne-box generale">
    <div class="label">MOYENNE GÉNÉRALE</div>
    <div class="value">{{ number_format($bulletin->moyenne_generale ?? 0, 2) }} / 20</div>
    <div style="margin-top:4px;font-size:9pt;opacity:0.9">Rang : {{ $bulletin->rang ?? '—' }} / {{ $bulletin->effectif_classe ?? '—' }}</div>
  </div>

  @if($bulletin->appreciation_gen)
    <div class="appreciation">
      <strong>Appréciation :</strong> {{ $bulletin->appreciation_gen }}
    </div>
  @endif

  <div class="footer">
    <p>Bulletin généré le {{ now()->format('d/m/Y') }} à {{ now()->format('H:i') }}</p>
    <p>{{ $tenant->adresse ?? '' }} • {{ $tenant->telephone ?? '' }} • {{ $tenant->email ?? '' }}</p>
  </div>
</body>
</html>
