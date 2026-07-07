<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; padding:10px; direction:rtl; }
  .page-break { page-break-after: always; }
  .conv { padding:14px; border-bottom:2px dashed #cbd5e1; margin-bottom:10px; }
  .conv:last-child { border-bottom:none; }
  .header { text-align:center; border-bottom:2px solid #1e3a5f; padding-bottom:10px; margin-bottom:12px; }
  .logo { font-size:14px; font-weight:bold; color:#1e3a5f; }
  .title-conv { font-size:13px; font-weight:bold; color:#1e3a5f; margin:8px 0; text-align:center; border:2px solid #1e3a5f; padding:6px; }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:10px; }
  .info-box { border:1px solid #e2e8f0; border-radius:4px; padding:5px 8px; background:#f8fafc; }
  .info-label { font-size:8px; color:#64748b; font-weight:bold; margin-bottom:2px; }
  .info-value { font-size:11px; font-weight:bold; color:#0f172a; }
  .matiere-table { width:100%; border-collapse:collapse; margin-top:8px; }
  .matiere-table th { background:#1e3a5f; color:#fff; padding:5px 8px; font-size:9px; text-align:right; }
  .matiere-table td { padding:4px 8px; border-bottom:1px solid #e2e8f0; font-size:9px; }
  .footer { margin-top:10px; text-align:center; font-size:8px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:6px; }
</style>
</head>
<body>
  @foreach($candidats as $candidat)
    <div class="conv @if(!$loop->last) page-break @endif">
      <div class="header">
        <div class="logo">الجمهورية الجزائرية الديمقراطية الشعبية</div>
        <div style="font-size:9px;color:#475569;">وزارة التربية الوطنية — ONEC</div>
      </div>
      <div class="title-conv">
        استدعاء — @if($session->type === 'BAC') بكالوريا @else BEM @endif {{ $session->annee_scolaire }}
      </div>
      <div class="info-grid">
        <div class="info-box">
          <div class="info-label">الاسم واللقب</div>
          <div class="info-value">{{ $candidat->nom }} {{ $candidat->prenom }}</div>
        </div>
        <div class="info-box">
          <div class="info-label">رقم التسجيل</div>
          <div class="info-value" style="color:#2563eb;">{{ $candidat->numero_inscription ?? '—' }}</div>
        </div>
        @if($candidat->salle)
        <div class="info-box" style="background:#dbeafe;border-color:#2563eb;">
          <div class="info-label">القاعة / المقعد</div>
          <div class="info-value" style="color:#1d4ed8;">{{ $candidat->salle->nom }} — {{ $candidat->rangee }}{{ $candidat->colonne }}</div>
        </div>
        @endif
        <div class="info-box">
          <div class="info-label">مركز الامتحان</div>
          <div class="info-value">{{ $session->nom_centre ?? '—' }}</div>
        </div>
      </div>
      <table class="matiere-table">
        <thead>
          <tr>
            <th>المادة</th>
            <th>التاريخ</th>
            <th>التوقيت</th>
            <th>المدة</th>
          </tr>
        </thead>
        <tbody>
          @foreach($epreuves as $ep)
          <tr>
            <td><strong>{{ $ep->matiere }}</strong></td>
            <td>{{ $ep->date_epreuve->format('d/m/Y') }}</td>
            <td>{{ $ep->heure_debut }} — {{ $ep->heure_fin }}</td>
            <td>{{ $ep->duree_minutes }} د</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div class="footer">EduGest DZ — {{ $session->nom_centre }}</div>
    </div>
  @endforeach
</body>
</html>
