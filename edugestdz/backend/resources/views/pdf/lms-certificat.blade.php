<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:DejaVu Sans, sans-serif; background:#fff; width:297mm; height:210mm; }
  .certificat {
    width:100%; height:100%; padding:20mm 25mm;
    border:8mm solid #1e3a5f;
    position:relative;
  }
  .certificat::before {
    content:'';
    position:absolute; inset:5mm;
    border:2px solid #2563eb;
  }
  .header { text-align:center; margin-bottom:8mm; }
  .school { font-size:14pt; color:#64748b; margin-bottom:2mm; }
  .title  { font-size:28pt; font-weight:bold; color:#1e3a5f; margin-bottom:2mm; }
  .subtitle { font-size:11pt; color:#475569; letter-spacing:3px; text-transform:uppercase; }
  .divider { border:none; border-top:2px solid #e2e8f0; margin:6mm 0; }
  .content { text-align:center; }
  .certifie { font-size:12pt; color:#64748b; margin-bottom:3mm; }
  .nom-eleve { font-size:24pt; font-weight:bold; color:#1e3a5f; margin-bottom:2mm; font-style:italic; }
  .for-completing { font-size:11pt; color:#64748b; margin-bottom:3mm; }
  .nom-cours { font-size:18pt; font-weight:bold; color:#2563eb; margin-bottom:2mm; }
  .details   { font-size:10pt; color:#94a3b8; margin-bottom:8mm; }
  .footer    { display:flex; justify-content:space-between; align-items:flex-end; margin-top:10mm; }
  .sig-bloc  { text-align:center; }
  .sig-line  { border-bottom:1px solid #1e3a5f; width:50mm; margin-bottom:2mm; }
  .sig-nom   { font-size:10pt; color:#475569; }
  .sig-role  { font-size:9pt; color:#94a3b8; }
  .badge     {
    width:25mm; height:25mm; border-radius:50%;
    background:#1e3a5f; display:flex; align-items:center;
    justify-content:center; margin:0 auto 2mm;
  }
  .badge-txt { color:#fff; font-size:7pt; text-align:center; font-weight:bold; line-height:1.3; }
  .date-bloc { text-align:center; }
  .date-val  { font-size:11pt; color:#1e3a5f; font-weight:bold; }
  .watermark {
    position:absolute; top:50%; left:50%;
    transform:translate(-50%,-50%) rotate(-30deg);
    font-size:60pt; color:#f0f9ff; opacity:.5;
    font-weight:900; pointer-events:none; z-index:0;
  }
</style>
</head>
<body>
<div class="certificat">
  <div class="watermark">🎓</div>
  <div class="header">
    <div class="school">🎓 EduGest DZ</div>
    <div class="title">Certificat de Complétion</div>
    <div class="subtitle">Certificate of Completion · شهادة إتمام</div>
  </div>
  <hr class="divider">
  <div class="content">
    <div class="certifie">Ce certificat est décerné à</div>
    <div class="nom-eleve">{{ $eleve->prenom }} {{ $eleve->nom }}</div>
    <div class="for-completing">pour avoir complété avec succès le cours</div>
    <div class="nom-cours">{{ $cours->titre }}</div>
    <div class="details">
      Matière : {{ $cours->matiere ?? '—' }} &nbsp;·&nbsp;
      Durée : {{ $cours->duree_estimee ?? 'N/A' }} &nbsp;·&nbsp;
      Progression : {{ $inscription->progression_pct }}%
    </div>
  </div>
  <div class="footer">
    <div class="sig-bloc">
      <div class="sig-line"></div>
      <div class="sig-nom">{{ $cours->enseignant->nom ?? 'Enseignant' }} {{ $cours->enseignant->prenom ?? '' }}</div>
      <div class="sig-role">Responsable du cours</div>
    </div>
    <div>
      <div class="badge"><div class="badge-txt">CERTIFIÉ<br>EDUGEST<br>DZ</div></div>
    </div>
    <div class="date-bloc">
      <div class="sig-line"></div>
      <div class="date-val">{{ $inscription->complete_le?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
      <div class="sig-role">Date d'obtention</div>
    </div>
  </div>
</div>
</body>
</html>
