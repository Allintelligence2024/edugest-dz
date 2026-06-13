{{-- resources/views/pdf/bulletin.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#2C3E50; }
    .header { background:linear-gradient(135deg,#1E5EBC,#27AE60); color:#fff; padding:20px 30px; display:flex; justify-content:space-between; align-items:center; }
    .header h1 { font-size:20px; }
    .header .annee { font-size:12px; opacity:0.9; }
    .info-eleve { display:flex; justify-content:space-between; padding:15px 30px; background:#F8FAFC; border-bottom:2px solid #DEE2E6; }
    .info-eleve div { font-size:11px; }
    .info-eleve strong { font-size:13px; }
    .section { padding:15px 30px; }
    .section h3 { color:#1E5EBC; font-size:12px; text-transform:uppercase; margin-bottom:10px; border-bottom:2px solid #1E5EBC; padding-bottom:5px; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#1E5EBC; color:#fff; padding:7px 10px; text-align:left; font-size:10px; }
    tbody td { padding:7px 10px; border-bottom:1px solid #F0F4F8; font-size:10px; }
    tbody tr:nth-child(even) { background:#F8FAFC; }
    .text-center { text-align:center; }
    .text-right { text-align:right; }
    .moyenne-box { text-align:center; padding:20px; background:linear-gradient(135deg,#EEF4FF,#EDFAF3); border-radius:12px; margin-top:10px; }
    .moyenne-box .note { font-size:36px; font-weight:bold; color:#1E5EBC; }
    .moyenne-box .label { font-size:11px; color:#6C757D; }
    .appreciation { text-align:center; font-size:13px; font-style:italic; color:#27AE60; margin-top:10px; }
    .footer { margin-top:20px; padding:12px 30px; background:#F8FAFC; border-top:2px solid #DEE2E6; text-align:center; font-size:9px; color:#ADB5BD; }
  </style>
</head>
<body>
<div class="header">
  <div>
    <h1>{{ $tenant->nom_etablissement }}</h1>
    <div class="annee">Année scolaire {{ $bulletin->annee_scolaire }}</div>
  </div>
  <div style="text-align:right;">
    <div style="font-size:16px; font-weight:bold;">Bulletin de notes</div>
    <div class="annee">Trimestre {{ str_replace('T', '', $bulletin->trimestre) }}</div>
  </div>
</div>
<div class="info-eleve">
  <div>
    <strong>{{ strtoupper($eleve->nom) }} {{ $eleve->prenom }}</strong><br>
    N°: {{ $eleve->numero_inscription }}<br>
    Niveau: {{ $eleve->niveau_scolaire }}
  </div>
  <div>
    <strong>{{ $groupe->matiere?->nom_fr ?? $groupe->nom }}</strong><br>
    Rang: {{ $bulletin->rang }}/{{ $bulletin->effectif_classe }}<br>
    Effectif: {{ $bulletin->effectif_classe }} élève(s)
  </div>
</div>
<div class="section">
  <h3>📝 Détail des notes</h3>
  @if($notes->count() > 0)
    @foreach($notes as $type => $notesType)
      <p style="margin:8px 0 4px; font-weight:bold; font-size:10px; color:#495057;">
        {{ ['devoir_classe'=>'Devoir en classe','devoir_maison'=>'Devoir maison','test_rapide'=>'Test rapide','examen_mensuel'=>'Examen mensuel','examen_module'=>'Examen module'][$type] ?? $type }}
      </p>
      <table>
        <thead><tr><th>Date</th><th>Titre</th><th class="text-center">Note</th><th class="text-center">Coeff.</th><th class="text-center">Appréciation</th></tr></thead>
        <tbody>
          @foreach($notesType as $note)
          <tr>
            <td>{{ \Carbon\Carbon::parse($note->evaluation->date_evaluation)->format('d/m') }}</td>
            <td>{{ $note->evaluation->titre }}</td>
            <td class="text-center">{{ $note->absent ? 'ABS' : $note->note . '/' . $note->evaluation->note_sur }}</td>
            <td class="text-center">{{ $note->evaluation->coefficient }}</td>
            <td class="text-center">{{ $note->appreciation ? ucfirst(str_replace('_',' ',$note->appreciation)) : '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endforeach
  @else
    <p style="text-align:center; color:#ADB5BD;">Aucune note enregistrée pour ce trimestre.</p>
  @endif
</div>
<div class="moyenne-box">
  <div class="label">Moyenne Générale</div>
  <div class="note">{{ number_format($bulletin->moyenne_generale, 2, ',', ' ') }}/20</div>
  <div class="appreciation">{{ $bulletin->appreciation_gen }}</div>
</div>
@if($presenceStats->count() > 0)
<div class="section">
  <h3>✅ Assiduité</h3>
  <table>
    <tr><td>Présences</td><td>{{ $presenceStats->get('présent', 0) }}</td><td>Absences</td><td>{{ $presenceStats->get('absent', 0) }}</td></tr>
    <tr><td>Retards</td><td>{{ $presenceStats->get('retard', 0) }}</td><td>Excusés</td><td>{{ $presenceStats->get('excusé', 0) }}</td></tr>
  </table>
</div>
@endif
<div class="footer">
  <p>{{ $tenant->nom_etablissement }} — {{ $tenant->adresse }} — {{ $tenant->telephone }}</p>
  <p>Généré le {{ now()->format('d/m/Y à H:i') }} — EduGest DZ</p>
</div>
</body>
</html>
