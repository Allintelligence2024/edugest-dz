<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#1e293b; padding:20px; direction:rtl; }
  .header { text-align:center; border-bottom:2px solid #1e3a5f; padding-bottom:14px; margin-bottom:16px; }
  .logo { font-size:18px; font-weight:bold; color:#1e3a5f; }
  .subtitle { font-size:12px; color:#475569; margin-top:4px; }
  .title-conv { font-size:16px; font-weight:bold; color:#1e3a5f; margin:12px 0; text-align:center; border:2px solid #1e3a5f; padding:8px; }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
  .info-box { border:1px solid #e2e8f0; border-radius:6px; padding:8px 10px; background:#f8fafc; }
  .info-label { font-size:9px; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
  .info-value { font-size:12px; font-weight:bold; color:#0f172a; }
  .matiere-table { width:100%; border-collapse:collapse; margin-top:12px; }
  .matiere-table th { background:#1e3a5f; color:#fff; padding:7px 10px; font-size:10px; text-align:right; }
  .matiere-table td { padding:6px 10px; border-bottom:1px solid #e2e8f0; font-size:10px; }
  .matiere-table tr:nth-child(even) td { background:#f8fafc; }
  .important { background:#fef9c3; border:1px solid #eab308; border-radius:6px; padding:10px; margin-top:14px; font-size:10px; }
  .footer { margin-top:16px; text-align:center; font-size:9px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
  .stamp-area { border:2px dashed #cbd5e1; border-radius:8px; height:60px; margin-top:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:9px; }
</style>
</head>
<body>

<div class="header">
  <div class="logo">🎓 الجمهورية الجزائرية الديمقراطية الشعبية</div>
  <div class="subtitle">وزارة التربية الوطنية — الديوان الوطني للامتحانات والمسابقات</div>
  <div class="subtitle" style="font-size:10px;margin-top:2px;">
    {{ $session->nom_centre ?? 'مركز الامتحان' }} — {{ $session->wilaya ?? '' }}
  </div>
</div>

<div class="title-conv">
  استدعاء للمشاركة في امتحان
  @if($session->type === 'BAC') شهادة البكالوريا @elseif($session->type === 'BEM') شهادة التعليم المتوسط @endif
  — دورة {{ $session->annee_scolaire }}
</div>

<div class="info-grid">
  <div class="info-box">
    <div class="info-label">الاسم واللقب</div>
    <div class="info-value">{{ $candidat->nom }} {{ $candidat->prenom }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">رقم التسجيل</div>
    <div class="info-value" style="color:#2563eb;font-size:14px;">{{ $candidat->numero_inscription ?? 'غير محدد' }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">تاريخ الميلاد</div>
    <div class="info-value">{{ $candidat->date_naissance ? $candidat->date_naissance->format('d/m/Y') : '—' }}</div>
  </div>
  <div class="info-box">
    <div class="info-label">مكان الميلاد</div>
    <div class="info-value">{{ $candidat->lieu_naissance ?? '—' }}</div>
  </div>
  @if($candidat->salle)
  <div class="info-box" style="background:#dbeafe;border-color:#2563eb;">
    <div class="info-label">القاعة</div>
    <div class="info-value" style="color:#1d4ed8;font-size:16px;">{{ $candidat->salle->nom }}</div>
  </div>
  <div class="info-box" style="background:#dbeafe;border-color:#2563eb;">
    <div class="info-label">المقعد</div>
    <div class="info-value" style="color:#1d4ed8;font-size:16px;">{{ $candidat->rangee }}{{ $candidat->colonne }}</div>
  </div>
  @endif
</div>

<table class="matiere-table">
  <thead>
    <tr>
      <th>المادة</th>
      <th>التاريخ</th>
      <th>التوقيت</th>
      <th>المدة</th>
      <th>المعامل</th>
    </tr>
  </thead>
  <tbody>
    @foreach($epreuves as $ep)
    <tr>
      <td><strong>{{ $ep->matiere }}</strong></td>
      <td>{{ $ep->date_epreuve->translatedFormat('l d/m/Y') }}</td>
      <td>{{ $ep->heure_debut }} — {{ $ep->heure_fin }}</td>
      <td>{{ $ep->duree_minutes }} دقيقة</td>
      <td style="text-align:center;font-weight:bold;">{{ $ep->coefficient }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="important">
  <strong>⚠️ تعليمات مهمة :</strong><br>
  • يفتح مركز الامتحان قبل ساعة من انطلاق الاختبار — لا يُسمح بالدخول بعد توزيع المواضيع<br>
  • يجب تقديم هذا الاستدعاء + بطاقة التعريف الوطنية إلزاميًا<br>
  • يُحظر إحضار أي جهاز إلكتروني أو وثيقة غير مُرخَّصة<br>
  • الدخول الصباحي: يحدد في 8:00 — انطلاق الاختبار: 8:30<br>
  • الدخول المسائي: يحدد في 14:00 — انطلاق الاختبار: 14:30
</div>

<div class="stamp-area">خانة ختم المركز</div>

<div class="footer">
  EduGest DZ · {{ $session->nom_centre }} · {{ $session->annee_scolaire }}
</div>

</body>
</html>
