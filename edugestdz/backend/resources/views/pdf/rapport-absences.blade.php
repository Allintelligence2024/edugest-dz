<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; padding: 20px; }
  .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3b82f6; padding-bottom: 12px; }
  .header h1 { font-size: 18px; color: #3b82f6; font-weight: bold; }
  .header .sub { font-size: 11px; color: #64748b; margin-top: 4px; }
  .meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 10px; color: #475569; }
  .stats { display: flex; gap: 16px; margin-bottom: 16px; }
  .stat-box { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center; }
  .stat-val { font-size: 22px; font-weight: bold; color: #3b82f6; }
  .stat-lbl { font-size: 9px; color: #64748b; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1e3a5f; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 7px 6px; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
  tr:nth-child(even) td { background: #f8fafc; }
  .alerte td { background: #fff7ed !important; }
  .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; }
  .badge-rouge { background: #fee2e2; color: #b91c1c; }
  .badge-orange { background: #fff7ed; color: #c2410c; }
  .badge-vert { background: #dcfce7; color: #15803d; }
  .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <h1>Rapport Absences Mensuel</h1>
  <div class="sub">Période : {{ $debut }} → {{ $fin }} &nbsp;·&nbsp; {{ $mois }}</div>
</div>

<div class="meta">
  <span>Total élèves : <strong>{{ $total_eleves }}</strong></span>
  <span>Élèves avec absences : <strong>{{ $data->count() }}</strong></span>
  <span>Total absences : <strong>{{ $nb_absences }}</strong></span>
  <span>Généré le : {{ $genere_le }}</span>
</div>

@if($data->isEmpty())
  <p style="text-align:center; color:#64748b; margin-top:40px;">
    ✅ Aucune absence enregistrée pour cette période.
  </p>
@else
  <table>
    <thead>
      <tr>
        <th>Élève</th>
        <th>Niveau</th>
        <th>Total</th>
        <th>Justifiées</th>
        <th>Non justifiées</th>
        <th>En attente</th>
        <th>Dates</th>
        <th>Statut</th>
      </tr>
    </thead>
    <tbody>
      @foreach($data as $row)
      <tr class="{{ $row['alerte'] ? 'alerte' : '' }}">
        <td><strong>{{ $row['eleve']->nom }} {{ $row['eleve']->prenom }}</strong></td>
        <td>{{ $row['eleve']->niveau_scolaire }}</td>
        <td style="font-weight:bold; color:#1d4ed8">{{ $row['total'] }}</td>
        <td style="color:#15803d">{{ $row['justifiees'] }}</td>
        <td style="color:#b91c1c">{{ $row['non_justifiees'] }}</td>
        <td style="color:#c2410c">{{ $row['en_attente'] }}</td>
        <td style="font-size:8px; color:#475569">{{ $row['dates'] }}</td>
        <td>
          @if($row['alerte'])
            <span class="badge badge-rouge">⚠ Alerte</span>
          @elseif($row['non_justifiees'] > 0)
            <span class="badge badge-orange">À justifier</span>
          @else
            <span class="badge badge-vert">OK</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="footer">
  EduGest DZ — Plateforme SaaS de gestion scolaire &nbsp;·&nbsp; app.edugest.dz &nbsp;·&nbsp;
  Document généré automatiquement — ne pas modifier
</div>

</body>
</html>
