# 🤖 MISSION DEEPSEEK — Dark/Light Theme + i18n complet (FR · AR · EN · DZ Darija)
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Objectif : Theme switcher + 4 langues dont la Darija Algérienne

---

## ANALYSE DU CODE EXISTANT (vérifié sur GitHub)

### Ce qui existe déjà
- `src/context/I18nContext.jsx` → importe `fr.json`, `ar.json`, `dz.json` ✅
- `useI18n()` hook → `t(key)`, `changeLang(lang)`, `lang` ✅
- RTL géré : `document.documentElement.dir = 'rtl'` pour ar/dz ✅
- `localStorage.getItem('lang')` pour persistance ✅
- `src/lang/fr.json` et `src/lang/ar.json` → existent (probablement partiels)
- `src/lang/dz.json` → existe (probablement vide ou partiel)

### Ce qui manque
1. **`en.json`** — anglais (langue manquante)
2. **`dz.json`** complet — Darija Algérienne (vocabulaire éducatif DZ)
3. **`fr.json`** et `ar.json` complets — toutes les clés de l'interface
4. **ThemeContext** — dark/light mode avec CSS variables + localStorage
5. **Sélecteur langue + thème** dans le Header (boutons visibles)
6. **Anti-flash** — le thème doit être appliqué avant React pour éviter le clignotement blanc

### RÈGLES ABSOLUES
1. Ne JAMAIS casser les routes dans App.jsx
2. Ne pas supprimer les imports existants
3. `useI18n()` reste compatible — juste enrichi avec `en`
4. Tester `npm run build` sans erreur avant de committer
5. RTL automatique pour ar et dz (déjà géré, juste vérifier)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
cd edugestdz/frontend
```

---

## ÉTAPE 1 — Fichier de traductions FR (français complet)

**Remplacer complètement :**
`edugestdz/frontend/src/lang/fr.json`

```json
{
  "app_name": "EduGest DZ",
  "app_subtitle": "Gestion Scolaire",
  "loading": "Chargement...",
  "save": "Enregistrer",
  "cancel": "Annuler",
  "delete": "Supprimer",
  "edit": "Modifier",
  "add": "Ajouter",
  "close": "Fermer",
  "confirm": "Confirmer",
  "back": "Retour",
  "next": "Suivant",
  "search": "Rechercher élève, facture...",
  "export": "Exporter",
  "import": "Importer",
  "print": "Imprimer",
  "download": "Télécharger",
  "send": "Envoyer",
  "view": "Voir",
  "create": "Créer",
  "generate": "Générer",
  "filter": "Filtrer",
  "all": "Tous",
  "yes": "Oui",
  "no": "Non",
  "total": "Total",
  "date": "Date",
  "actions": "Actions",
  "status": "Statut",
  "name": "Nom",
  "email": "Email",
  "phone": "Téléphone",
  "address": "Adresse",
  "welcome": "Bonjour, {name} 👋",
  "today": "Aujourd'hui",
  "this_week": "Cette semaine",
  "this_month": "Ce mois",
  "this_year": "Cette année",
  "per_page": "Lignes par page",
  "showing": "Affichage {from}-{to} sur {total}",
  "no_data": "Aucune donnée disponible",
  "error_network": "Impossible de joindre le serveur.",
  "error_auth": "Session expirée. Reconnectez-vous.",
  "success_saved": "Enregistré avec succès",
  "success_deleted": "Supprimé avec succès",
  "confirm_delete": "Êtes-vous sûr de vouloir supprimer ?",

  "nav_dashboard": "Tableau de bord",
  "nav_students": "Élèves",
  "nav_teachers": "Enseignants",
  "nav_planning": "Planning",
  "nav_attendance": "Présences",
  "nav_absences": "Absences",
  "nav_tickets": "Billets",
  "nav_notes": "Notes",
  "nav_bulletins": "Bulletins",
  "nav_diagnostic": "Diagnostic Niveau",
  "nav_finance": "Finance",
  "nav_budget": "Budget",
  "nav_transport": "Transport",
  "nav_canteen": "Cantine",
  "nav_stock": "Stock",
  "nav_staff": "Personnel",
  "nav_maintenance": "Entretien",
  "nav_surveillance": "Surveillance",
  "nav_pointage": "Pointage",
  "nav_messages": "Messages",
  "nav_campaigns": "Campagnes",
  "nav_marketplace": "Marketplace",
  "nav_profile": "Mon Profil",
  "nav_audit": "Journal Audit",
  "nav_superadmin": "Super-Admin",
  "nav_logout": "Déconnexion",

  "section_main": "Principal",
  "section_pedagogy": "Pédagogie",
  "section_finance": "Finance",
  "section_management": "Gestion Centre",
  "section_communication": "Communication",
  "section_settings": "Paramètres",

  "login_title": "Connexion",
  "login_email": "Adresse email",
  "login_password": "Mot de passe",
  "login_submit": "Se connecter",
  "login_error": "Email ou mot de passe incorrect",
  "login_loading": "Connexion en cours...",
  "login_forgot": "Mot de passe oublié ?",
  "login_subtitle": "Plateforme de gestion scolaire",

  "dashboard_title": "Tableau de bord",
  "dashboard_students_active": "Élèves actifs",
  "dashboard_revenue_month": "CA ce mois",
  "dashboard_absences_today": "Absences aujourd'hui",
  "dashboard_unpaid": "Impayés",
  "dashboard_sessions_today": "Séances aujourd'hui",
  "dashboard_teachers_present": "Enseignants présents",
  "dashboard_buses_active": "Bus actifs",
  "dashboard_critical_students": "Élèves niveau critique",
  "dashboard_quick_actions": "Actions rapides",
  "dashboard_recent_activity": "Activité récente",
  "dashboard_attendance_today": "Présence aujourd'hui",
  "dashboard_urgent_actions": "Actions urgentes",
  "dashboard_revenue_evolution": "Évolution CA — 6 mois",

  "students_title": "Gestion des Élèves",
  "students_new": "+ Nouvel élève",
  "students_search": "Rechercher par nom, prénom, ID...",
  "students_level": "Niveau",
  "students_status": "Statut",
  "students_average": "Moyenne",
  "students_absences": "Absences/mois",
  "students_diagnostic": "Diagnostic",
  "students_enrolled": "Inscrit le",
  "students_active": "Actif",
  "students_inactive": "Inactif",
  "students_suspended": "Suspendu",
  "students_top": "Top Élèves",
  "students_required_actions": "Actions requises",

  "finance_title": "Finance & Paiements",
  "finance_new_invoice": "+ Nouvelle facture",
  "finance_revenue": "CA ce mois",
  "finance_paid": "Factures payées",
  "finance_unpaid": "Impayés",
  "finance_target": "Objectif annuel",
  "finance_payment_modes": "Modes de paiement",
  "finance_cash": "Espèces",
  "finance_cib": "CIB / Dahabia",
  "finance_transfer": "Virement",
  "finance_cheque": "Chèque",
  "finance_status_paid": "Payée",
  "finance_status_issued": "Émise",
  "finance_status_late": "En retard",
  "finance_status_partial": "Partiel",
  "finance_status_cancelled": "Annulée",
  "finance_reminders": "Relances automatiques",
  "finance_collect": "Encaisser",
  "finance_remind": "Relancer",

  "absences_title": "Absences Journalières",
  "absences_declare": "Déclarer une absence",
  "absences_justify": "Justifier",
  "absences_justified": "Justifiée",
  "absences_unjustified": "Non justifiée",
  "absences_pending": "En attente",
  "absences_sms_sent": "SMS envoyé",
  "absences_report": "Rapport PDF",

  "diagnostic_title": "Diagnostic de Niveau",
  "diagnostic_excellent": "Excellent",
  "diagnostic_normal": "Normal",
  "diagnostic_watch": "Vigilance",
  "diagnostic_danger": "Danger",
  "diagnostic_critical": "Critique",
  "diagnostic_remediation": "Plan de rattrapage",
  "diagnostic_convocation": "Convoquer parents",
  "diagnostic_analyze_all": "Analyser tous",
  "diagnostic_risk_score": "Score risque",
  "diagnostic_recommendations": "Recommandations",

  "bulletin_title": "Bulletins",
  "bulletin_generate": "Générer",
  "bulletin_download": "Télécharger PDF",
  "bulletin_send_sms": "Envoyer SMS + Push",
  "bulletin_mention": "Mention",
  "bulletin_rank": "Rang",
  "bulletin_class_avg": "Moy. classe",
  "bulletin_appreciation": "Appréciation",

  "transport_title": "Transport Scolaire",
  "transport_circuits": "Circuits",
  "transport_stops": "Arrêts",
  "transport_students": "Élèves inscrits",
  "transport_pointage": "Pointage bus",
  "transport_go": "Aller",
  "transport_return": "Retour",
  "transport_capacity": "Capacité",
  "transport_active": "Actif",

  "canteen_title": "Cantine",
  "canteen_menus": "Menus",
  "canteen_subscriptions": "Inscriptions",
  "canteen_pointage": "Pointage repas",
  "canteen_lunch": "Déjeuner",
  "canteen_stock": "Stock cuisine",

  "surveillance_title": "Surveillance Dahua",
  "surveillance_alerts": "Alertes",
  "surveillance_cameras": "Caméras",
  "surveillance_treat": "Traiter",
  "surveillance_critical": "Critique",
  "surveillance_all_ok": "Système opérationnel",
  "surveillance_add_camera": "Ajouter une caméra",

  "theme_dark": "Mode sombre",
  "theme_light": "Mode clair",
  "theme_system": "Système",
  "lang_select": "Langue",
  "lang_fr": "Français",
  "lang_ar": "العربية",
  "lang_en": "English",
  "lang_dz": "الدارجة",

  "profile_title": "Mon Profil",
  "profile_name": "Nom & Prénom",
  "profile_email": "Email",
  "profile_phone": "Téléphone",
  "profile_password": "Mot de passe",
  "profile_current_password": "Mot de passe actuel",
  "profile_new_password": "Nouveau mot de passe",
  "profile_confirm_password": "Confirmer le mot de passe",
  "profile_save": "Enregistrer les modifications",
  "profile_2fa": "Double authentification",
  "profile_2fa_enable": "Activer la 2FA",

  "notifications_title": "Notifications",
  "notifications_none": "Aucune notification non lue",
  "notifications_mark_all_read": "Tout marquer comme lu",
  "notifications_view_all": "Tout voir"
}
```

---

## ÉTAPE 2 — Fichier de traductions EN (anglais)

**Créer :**
`edugestdz/frontend/src/lang/en.json`

```json
{
  "app_name": "EduGest DZ",
  "app_subtitle": "School Management",
  "loading": "Loading...",
  "save": "Save",
  "cancel": "Cancel",
  "delete": "Delete",
  "edit": "Edit",
  "add": "Add",
  "close": "Close",
  "confirm": "Confirm",
  "back": "Back",
  "next": "Next",
  "search": "Search student, invoice...",
  "export": "Export",
  "import": "Import",
  "print": "Print",
  "download": "Download",
  "send": "Send",
  "view": "View",
  "create": "Create",
  "generate": "Generate",
  "filter": "Filter",
  "all": "All",
  "yes": "Yes",
  "no": "No",
  "total": "Total",
  "date": "Date",
  "actions": "Actions",
  "status": "Status",
  "name": "Name",
  "email": "Email",
  "phone": "Phone",
  "address": "Address",
  "welcome": "Hello, {name} 👋",
  "today": "Today",
  "this_week": "This week",
  "this_month": "This month",
  "this_year": "This year",
  "per_page": "Rows per page",
  "showing": "Showing {from}-{to} of {total}",
  "no_data": "No data available",
  "error_network": "Cannot reach the server.",
  "error_auth": "Session expired. Please log in again.",
  "success_saved": "Saved successfully",
  "success_deleted": "Deleted successfully",
  "confirm_delete": "Are you sure you want to delete?",

  "nav_dashboard": "Dashboard",
  "nav_students": "Students",
  "nav_teachers": "Teachers",
  "nav_planning": "Schedule",
  "nav_attendance": "Attendance",
  "nav_absences": "Absences",
  "nav_tickets": "Tickets",
  "nav_notes": "Grades",
  "nav_bulletins": "Report Cards",
  "nav_diagnostic": "Level Diagnostic",
  "nav_finance": "Finance",
  "nav_budget": "Budget",
  "nav_transport": "Transport",
  "nav_canteen": "Cafeteria",
  "nav_stock": "Inventory",
  "nav_staff": "Staff",
  "nav_maintenance": "Maintenance",
  "nav_surveillance": "Surveillance",
  "nav_pointage": "Attendance Tracking",
  "nav_messages": "Messages",
  "nav_campaigns": "Campaigns",
  "nav_marketplace": "Marketplace",
  "nav_profile": "My Profile",
  "nav_audit": "Audit Log",
  "nav_superadmin": "Super-Admin",
  "nav_logout": "Log out",

  "section_main": "Main",
  "section_pedagogy": "Academics",
  "section_finance": "Finance",
  "section_management": "Center Management",
  "section_communication": "Communication",
  "section_settings": "Settings",

  "login_title": "Sign In",
  "login_email": "Email address",
  "login_password": "Password",
  "login_submit": "Sign in",
  "login_error": "Incorrect email or password",
  "login_loading": "Signing in...",
  "login_forgot": "Forgot password?",
  "login_subtitle": "School management platform",

  "dashboard_title": "Dashboard",
  "dashboard_students_active": "Active students",
  "dashboard_revenue_month": "Revenue this month",
  "dashboard_absences_today": "Absences today",
  "dashboard_unpaid": "Overdue",
  "dashboard_sessions_today": "Sessions today",
  "dashboard_teachers_present": "Teachers present",
  "dashboard_buses_active": "Active buses",
  "dashboard_critical_students": "Critical level students",
  "dashboard_quick_actions": "Quick actions",
  "dashboard_recent_activity": "Recent activity",
  "dashboard_attendance_today": "Attendance today",
  "dashboard_urgent_actions": "Urgent actions",
  "dashboard_revenue_evolution": "Revenue — last 6 months",

  "students_title": "Student Management",
  "students_new": "+ New student",
  "students_search": "Search by name, ID...",
  "students_level": "Level",
  "students_status": "Status",
  "students_average": "Average",
  "students_absences": "Absences/month",
  "students_diagnostic": "Diagnostic",
  "students_enrolled": "Enrolled on",
  "students_active": "Active",
  "students_inactive": "Inactive",
  "students_suspended": "Suspended",
  "students_top": "Top Students",
  "students_required_actions": "Required actions",

  "finance_title": "Finance & Payments",
  "finance_new_invoice": "+ New invoice",
  "finance_revenue": "Revenue this month",
  "finance_paid": "Paid invoices",
  "finance_unpaid": "Overdue",
  "finance_target": "Annual target",
  "finance_payment_modes": "Payment methods",
  "finance_cash": "Cash",
  "finance_cib": "CIB / Dahabia",
  "finance_transfer": "Bank transfer",
  "finance_cheque": "Cheque",
  "finance_status_paid": "Paid",
  "finance_status_issued": "Issued",
  "finance_status_late": "Overdue",
  "finance_status_partial": "Partial",
  "finance_status_cancelled": "Cancelled",
  "finance_reminders": "Automatic reminders",
  "finance_collect": "Collect",
  "finance_remind": "Remind",

  "absences_title": "Daily Absences",
  "absences_declare": "Report absence",
  "absences_justify": "Justify",
  "absences_justified": "Justified",
  "absences_unjustified": "Unjustified",
  "absences_pending": "Pending",
  "absences_sms_sent": "SMS sent",
  "absences_report": "PDF Report",

  "diagnostic_title": "Level Diagnostic",
  "diagnostic_excellent": "Excellent",
  "diagnostic_normal": "Normal",
  "diagnostic_watch": "Watch",
  "diagnostic_danger": "At Risk",
  "diagnostic_critical": "Critical",
  "diagnostic_remediation": "Remediation plan",
  "diagnostic_convocation": "Summon parents",
  "diagnostic_analyze_all": "Analyze all",
  "diagnostic_risk_score": "Risk score",
  "diagnostic_recommendations": "Recommendations",

  "bulletin_title": "Report Cards",
  "bulletin_generate": "Generate",
  "bulletin_download": "Download PDF",
  "bulletin_send_sms": "Send SMS + Push",
  "bulletin_mention": "Grade",
  "bulletin_rank": "Rank",
  "bulletin_class_avg": "Class avg.",
  "bulletin_appreciation": "Comment",

  "transport_title": "School Transport",
  "transport_circuits": "Routes",
  "transport_stops": "Stops",
  "transport_students": "Enrolled students",
  "transport_pointage": "Bus check-in",
  "transport_go": "Morning",
  "transport_return": "Afternoon",
  "transport_capacity": "Capacity",
  "transport_active": "Active",

  "canteen_title": "Cafeteria",
  "canteen_menus": "Menus",
  "canteen_subscriptions": "Subscriptions",
  "canteen_pointage": "Meal check-in",
  "canteen_lunch": "Lunch",
  "canteen_stock": "Kitchen stock",

  "surveillance_title": "Dahua Surveillance",
  "surveillance_alerts": "Alerts",
  "surveillance_cameras": "Cameras",
  "surveillance_treat": "Mark as handled",
  "surveillance_critical": "Critical",
  "surveillance_all_ok": "System operational",
  "surveillance_add_camera": "Add a camera",

  "theme_dark": "Dark mode",
  "theme_light": "Light mode",
  "theme_system": "System",
  "lang_select": "Language",
  "lang_fr": "Français",
  "lang_ar": "العربية",
  "lang_en": "English",
  "lang_dz": "الدارجة",

  "profile_title": "My Profile",
  "profile_name": "Full name",
  "profile_email": "Email",
  "profile_phone": "Phone",
  "profile_password": "Password",
  "profile_current_password": "Current password",
  "profile_new_password": "New password",
  "profile_confirm_password": "Confirm password",
  "profile_save": "Save changes",
  "profile_2fa": "Two-factor authentication",
  "profile_2fa_enable": "Enable 2FA",

  "notifications_title": "Notifications",
  "notifications_none": "No unread notifications",
  "notifications_mark_all_read": "Mark all as read",
  "notifications_view_all": "View all"
}
```

---

## ÉTAPE 3 — Fichier de traductions AR (arabe MSA)

**Remplacer complètement :**
`edugestdz/frontend/src/lang/ar.json`

```json
{
  "app_name": "إيدو جيست DZ",
  "app_subtitle": "إدارة المؤسسات التعليمية",
  "loading": "جاري التحميل...",
  "save": "حفظ",
  "cancel": "إلغاء",
  "delete": "حذف",
  "edit": "تعديل",
  "add": "إضافة",
  "close": "إغلاق",
  "confirm": "تأكيد",
  "back": "رجوع",
  "next": "التالي",
  "search": "البحث عن تلميذ أو فاتورة...",
  "export": "تصدير",
  "import": "استيراد",
  "print": "طباعة",
  "download": "تنزيل",
  "send": "إرسال",
  "view": "عرض",
  "create": "إنشاء",
  "generate": "توليد",
  "filter": "تصفية",
  "all": "الكل",
  "yes": "نعم",
  "no": "لا",
  "total": "المجموع",
  "date": "التاريخ",
  "actions": "الإجراءات",
  "status": "الحالة",
  "name": "الاسم",
  "email": "البريد الإلكتروني",
  "phone": "الهاتف",
  "address": "العنوان",
  "welcome": "مرحباً {name} 👋",
  "today": "اليوم",
  "this_week": "هذا الأسبوع",
  "this_month": "هذا الشهر",
  "this_year": "هذه السنة",
  "per_page": "عدد الصفوف في الصفحة",
  "showing": "عرض {from}-{to} من {total}",
  "no_data": "لا توجد بيانات",
  "error_network": "تعذّر الوصول إلى الخادم.",
  "error_auth": "انتهت الجلسة. يرجى إعادة تسجيل الدخول.",
  "success_saved": "تم الحفظ بنجاح",
  "success_deleted": "تم الحذف بنجاح",
  "confirm_delete": "هل أنت متأكد من الحذف؟",

  "nav_dashboard": "لوحة التحكم",
  "nav_students": "التلاميذ",
  "nav_teachers": "الأساتذة",
  "nav_planning": "التوقيت",
  "nav_attendance": "الحضور",
  "nav_absences": "الغيابات",
  "nav_tickets": "التذاكر",
  "nav_notes": "النقاط",
  "nav_bulletins": "كشوف النقاط",
  "nav_diagnostic": "تشخيص المستوى",
  "nav_finance": "المالية",
  "nav_budget": "الميزانية",
  "nav_transport": "النقل المدرسي",
  "nav_canteen": "المطعم",
  "nav_stock": "المخزون",
  "nav_staff": "الموظفون",
  "nav_maintenance": "الصيانة",
  "nav_surveillance": "المراقبة",
  "nav_pointage": "تسجيل الحضور",
  "nav_messages": "الرسائل",
  "nav_campaigns": "الحملات",
  "nav_marketplace": "السوق",
  "nav_profile": "ملفي الشخصي",
  "nav_audit": "سجل التدقيق",
  "nav_superadmin": "المشرف العام",
  "nav_logout": "تسجيل الخروج",

  "section_main": "الرئيسية",
  "section_pedagogy": "البيداغوجيا",
  "section_finance": "المالية",
  "section_management": "إدارة المركز",
  "section_communication": "التواصل",
  "section_settings": "الإعدادات",

  "login_title": "تسجيل الدخول",
  "login_email": "البريد الإلكتروني",
  "login_password": "كلمة المرور",
  "login_submit": "دخول",
  "login_error": "البريد الإلكتروني أو كلمة المرور غير صحيحة",
  "login_loading": "جاري الدخول...",
  "login_forgot": "نسيت كلمة المرور؟",
  "login_subtitle": "منصة إدارة المؤسسات التعليمية",

  "dashboard_title": "لوحة التحكم",
  "dashboard_students_active": "التلاميذ النشطون",
  "dashboard_revenue_month": "رقم الأعمال هذا الشهر",
  "dashboard_absences_today": "الغيابات اليوم",
  "dashboard_unpaid": "المتأخرات",
  "dashboard_sessions_today": "الحصص اليوم",
  "dashboard_teachers_present": "الأساتذة الحاضرون",
  "dashboard_buses_active": "الحافلات النشطة",
  "dashboard_critical_students": "تلاميذ في مستوى حرج",
  "dashboard_quick_actions": "إجراءات سريعة",
  "dashboard_recent_activity": "النشاط الأخير",
  "dashboard_attendance_today": "الحضور اليوم",
  "dashboard_urgent_actions": "إجراءات عاجلة",
  "dashboard_revenue_evolution": "تطور رقم الأعمال — 6 أشهر",

  "students_title": "إدارة التلاميذ",
  "students_new": "+ تلميذ جديد",
  "students_search": "البحث بالاسم أو المعرّف...",
  "students_level": "المستوى",
  "students_status": "الحالة",
  "students_average": "المعدل",
  "students_absences": "الغيابات/الشهر",
  "students_diagnostic": "التشخيص",
  "students_enrolled": "تاريخ التسجيل",
  "students_active": "نشط",
  "students_inactive": "غير نشط",
  "students_suspended": "موقوف",
  "students_top": "أفضل التلاميذ",
  "students_required_actions": "إجراءات مطلوبة",

  "finance_title": "المالية والمدفوعات",
  "finance_new_invoice": "+ فاتورة جديدة",
  "finance_revenue": "رقم الأعمال هذا الشهر",
  "finance_paid": "الفواتير المدفوعة",
  "finance_unpaid": "المتأخرات",
  "finance_target": "الهدف السنوي",
  "finance_payment_modes": "طرق الدفع",
  "finance_cash": "نقدًا",
  "finance_cib": "CIB / داهابيا",
  "finance_transfer": "تحويل بنكي",
  "finance_cheque": "شيك",
  "finance_status_paid": "مدفوعة",
  "finance_status_issued": "صادرة",
  "finance_status_late": "متأخرة",
  "finance_status_partial": "جزئية",
  "finance_status_cancelled": "ملغاة",
  "finance_reminders": "تذكيرات تلقائية",
  "finance_collect": "تحصيل",
  "finance_remind": "تذكير",

  "absences_title": "الغيابات اليومية",
  "absences_declare": "الإعلان عن غياب",
  "absences_justify": "تبرير",
  "absences_justified": "مبرر",
  "absences_unjustified": "غير مبرر",
  "absences_pending": "قيد الانتظار",
  "absences_sms_sent": "تم إرسال الرسالة",
  "absences_report": "تقرير PDF",

  "diagnostic_title": "تشخيص المستوى",
  "diagnostic_excellent": "ممتاز",
  "diagnostic_normal": "عادي",
  "diagnostic_watch": "مراقبة",
  "diagnostic_danger": "خطر",
  "diagnostic_critical": "حرج",
  "diagnostic_remediation": "خطة الدعم",
  "diagnostic_convocation": "استدعاء الأولياء",
  "diagnostic_analyze_all": "تحليل الجميع",
  "diagnostic_risk_score": "درجة الخطر",
  "diagnostic_recommendations": "التوصيات",

  "bulletin_title": "كشوف النقاط",
  "bulletin_generate": "إنشاء",
  "bulletin_download": "تنزيل PDF",
  "bulletin_send_sms": "إرسال رسالة + إشعار",
  "bulletin_mention": "الملاحظة",
  "bulletin_rank": "الرتبة",
  "bulletin_class_avg": "معدل القسم",
  "bulletin_appreciation": "التقدير",

  "transport_title": "النقل المدرسي",
  "transport_circuits": "المسارات",
  "transport_stops": "المحطات",
  "transport_students": "التلاميذ المسجلون",
  "transport_pointage": "تسجيل الحافلة",
  "transport_go": "ذهاب",
  "transport_return": "إياب",
  "transport_capacity": "الطاقة الاستيعابية",
  "transport_active": "نشط",

  "canteen_title": "المطعم المدرسي",
  "canteen_menus": "قوائم الطعام",
  "canteen_subscriptions": "الاشتراكات",
  "canteen_pointage": "تسجيل الوجبات",
  "canteen_lunch": "الغداء",
  "canteen_stock": "مخزون المطبخ",

  "surveillance_title": "مراقبة داهوا",
  "surveillance_alerts": "التنبيهات",
  "surveillance_cameras": "الكاميرات",
  "surveillance_treat": "معالجة",
  "surveillance_critical": "حرج",
  "surveillance_all_ok": "النظام يعمل بشكل طبيعي",
  "surveillance_add_camera": "إضافة كاميرا",

  "theme_dark": "الوضع الداكن",
  "theme_light": "الوضع الفاتح",
  "theme_system": "النظام",
  "lang_select": "اللغة",
  "lang_fr": "Français",
  "lang_ar": "العربية",
  "lang_en": "English",
  "lang_dz": "الدارجة",

  "profile_title": "ملفي الشخصي",
  "profile_name": "الاسم الكامل",
  "profile_email": "البريد الإلكتروني",
  "profile_phone": "الهاتف",
  "profile_password": "كلمة المرور",
  "profile_current_password": "كلمة المرور الحالية",
  "profile_new_password": "كلمة المرور الجديدة",
  "profile_confirm_password": "تأكيد كلمة المرور",
  "profile_save": "حفظ التغييرات",
  "profile_2fa": "التحقق بخطوتين",
  "profile_2fa_enable": "تفعيل",

  "notifications_title": "الإشعارات",
  "notifications_none": "لا توجد إشعارات غير مقروءة",
  "notifications_mark_all_read": "تحديد الكل كمقروء",
  "notifications_view_all": "عرض الكل"
}
```

---

## ÉTAPE 4 — Fichier de traductions DZ (Darija Algérienne)

> **Note sur la Darija Algérienne :**
> Il n'existe pas de standard i18n officiel pour la Darija algérienne.
> On utilise l'écriture arabe (pas le Arabizi/latin) car c'est la forme
> la plus lisible pour les utilisateurs algériens non-latinisants.
> Les traductions sont en dialecte oranais/algérois courant,
> adapté au contexte scolaire. Direction RTL comme l'arabe.

**Remplacer complètement :**
`edugestdz/frontend/src/lang/dz.json`

```json
{
  "_comment": "Darija Algérienne — Dialecte scolaire — Direction RTL",
  "app_name": "EduGest DZ",
  "app_subtitle": "تسيير المدارس",
  "loading": "يتحمّل...",
  "save": "سجّل",
  "cancel": "اقطع",
  "delete": "امسح",
  "edit": "بدّل",
  "add": "زيد",
  "close": "سكّر",
  "confirm": "وافق",
  "back": "ارجع",
  "next": "إيّه",
  "search": "دوّر على التلميذ ولا الفاتورة...",
  "export": "صدّر",
  "import": "استورد",
  "print": "طبع",
  "download": "نزّل",
  "send": "عيّط",
  "view": "شوف",
  "create": "درك",
  "generate": "ولّد",
  "filter": "صفّي",
  "all": "الكل",
  "yes": "إيّه",
  "no": "لا",
  "total": "المجموع",
  "date": "التاريخ",
  "actions": "العمليات",
  "status": "الحالة",
  "name": "الاسم",
  "email": "الإيميل",
  "phone": "التيليفون",
  "address": "العنوان",
  "welcome": "أهلاً {name} 👋",
  "today": "اليوم",
  "this_week": "هذا الأسبوع",
  "this_month": "هذا الشهر",
  "this_year": "هاد العام",
  "per_page": "عدد الصفوف",
  "showing": "كاين {from}-{to} من {total}",
  "no_data": "ماكاين والو",
  "error_network": "ما وصلناش للسيرفر.",
  "error_auth": "الجلسة انتهات. ادخل مرة أخرى.",
  "success_saved": "تسجّل مزيان",
  "success_deleted": "تمسح",
  "confirm_delete": "واش حاب تمسح؟",

  "nav_dashboard": "الداشبورد",
  "nav_students": "التلامذة",
  "nav_teachers": "الأساتذة",
  "nav_planning": "التوقيت",
  "nav_attendance": "الحضور",
  "nav_absences": "الغيابات",
  "nav_tickets": "التذاكر",
  "nav_notes": "النقاط",
  "nav_bulletins": "كشوف النقاط",
  "nav_diagnostic": "تشخيص المستوى",
  "nav_finance": "الحساب",
  "nav_budget": "الميزانية",
  "nav_transport": "الكار",
  "nav_canteen": "الكانطين",
  "nav_stock": "المخزن",
  "nav_staff": "العمال",
  "nav_maintenance": "الصيانة",
  "nav_surveillance": "المراقبة",
  "nav_pointage": "البصمة",
  "nav_messages": "الرسائل",
  "nav_campaigns": "الحملات",
  "nav_marketplace": "السوق",
  "nav_profile": "حسابي",
  "nav_audit": "السجل",
  "nav_superadmin": "المشرف العام",
  "nav_logout": "خرج",

  "section_main": "الرئيسي",
  "section_pedagogy": "البيداغوجيا",
  "section_finance": "الحساب",
  "section_management": "تسيير المركز",
  "section_communication": "التواصل",
  "section_settings": "الإعدادات",

  "login_title": "ادخل",
  "login_email": "الإيميل",
  "login_password": "كلمة السر",
  "login_submit": "دخل",
  "login_error": "الإيميل ولا كلمة السر غلط",
  "login_loading": "كيدخل...",
  "login_forgot": "نسيت كلمة السر؟",
  "login_subtitle": "منصة تسيير المدارس",

  "dashboard_title": "الداشبورد",
  "dashboard_students_active": "التلامذة النشطين",
  "dashboard_revenue_month": "الدخل هذا الشهر",
  "dashboard_absences_today": "الغيابات اليوم",
  "dashboard_unpaid": "المتأخرات",
  "dashboard_sessions_today": "الدروس اليوم",
  "dashboard_teachers_present": "الأساتذة الحاضرين",
  "dashboard_buses_active": "الكارات النشطة",
  "dashboard_critical_students": "تلامذة في خطر",
  "dashboard_quick_actions": "أسرع الأوامر",
  "dashboard_recent_activity": "آخر النشاطات",
  "dashboard_attendance_today": "الحضور اليوم",
  "dashboard_urgent_actions": "أوامر عاجلة",
  "dashboard_revenue_evolution": "الدخل — 6 أشهر",

  "students_title": "التلامذة",
  "students_new": "+ تلميذ جديد",
  "students_search": "دوّر بالاسم ولا الرقم...",
  "students_level": "المستوى",
  "students_status": "الحالة",
  "students_average": "المعدل",
  "students_absences": "الغيابات/الشهر",
  "students_diagnostic": "التشخيص",
  "students_enrolled": "تاريخ التسجيل",
  "students_active": "نشط",
  "students_inactive": "موقوف",
  "students_suspended": "محروم",
  "students_top": "أحسن التلامذة",
  "students_required_actions": "أوامر لازمة",

  "finance_title": "الحساب والمدفوعات",
  "finance_new_invoice": "+ فاتورة جديدة",
  "finance_revenue": "الدخل هذا الشهر",
  "finance_paid": "الفواتير المدفوعة",
  "finance_unpaid": "اللي ما دفعوش",
  "finance_target": "الهدف السنوي",
  "finance_payment_modes": "طرق الدفع",
  "finance_cash": "ورق (كاش)",
  "finance_cib": "CIB / داهابيا",
  "finance_transfer": "تحويل بنكي",
  "finance_cheque": "شيك",
  "finance_status_paid": "مدفوعة",
  "finance_status_issued": "صادرة",
  "finance_status_late": "متأخرة",
  "finance_status_partial": "ناقصة",
  "finance_status_cancelled": "ملغاة",
  "finance_reminders": "التذكيرات التلقائية",
  "finance_collect": "حصّل",
  "finance_remind": "ذكّر",

  "absences_title": "الغيابات اليومية",
  "absences_declare": "سجّل غياب",
  "absences_justify": "برّر",
  "absences_justified": "مبرّر",
  "absences_unjustified": "بلا سبب",
  "absences_pending": "قيد الانتظار",
  "absences_sms_sent": "تبعثلهم SMS",
  "absences_report": "تقرير PDF",

  "diagnostic_title": "تشخيص المستوى",
  "diagnostic_excellent": "ممتاز",
  "diagnostic_normal": "عادي",
  "diagnostic_watch": "مراقبة",
  "diagnostic_danger": "في خطر",
  "diagnostic_critical": "خايب برشا",
  "diagnostic_remediation": "خطة الدعم",
  "diagnostic_convocation": "عيّط للوالدين",
  "diagnostic_analyze_all": "حلّل الجميع",
  "diagnostic_risk_score": "نقطة الخطر",
  "diagnostic_recommendations": "النصائح",

  "bulletin_title": "كشوف النقاط",
  "bulletin_generate": "ولّد",
  "bulletin_download": "نزّل PDF",
  "bulletin_send_sms": "عيّط SMS + إشعار",
  "bulletin_mention": "الملاحظة",
  "bulletin_rank": "الترتيب",
  "bulletin_class_avg": "معدل القسم",
  "bulletin_appreciation": "التقدير",

  "transport_title": "الكار المدرسي",
  "transport_circuits": "المسارات",
  "transport_stops": "المحطات",
  "transport_students": "التلامذة المسجلين",
  "transport_pointage": "بصمة الكار",
  "transport_go": "ذهاب",
  "transport_return": "إياب",
  "transport_capacity": "الطاقة",
  "transport_active": "نشط",

  "canteen_title": "الكانطين",
  "canteen_menus": "القوائم",
  "canteen_subscriptions": "الاشتراكات",
  "canteen_pointage": "بصمة الوجبة",
  "canteen_lunch": "الغدا",
  "canteen_stock": "مخزن المطبخ",

  "surveillance_title": "مراقبة داهوا",
  "surveillance_alerts": "التنبيهات",
  "surveillance_cameras": "الكاميرات",
  "surveillance_treat": "عالج",
  "surveillance_critical": "خطر",
  "surveillance_all_ok": "كل شي يخدم مزيان",
  "surveillance_add_camera": "زيد كاميرا",

  "theme_dark": "الوضع الداكن",
  "theme_light": "الوضع الفاتح",
  "theme_system": "تلقائي",
  "lang_select": "اللغة",
  "lang_fr": "Français",
  "lang_ar": "العربية",
  "lang_en": "English",
  "lang_dz": "الدارجة",

  "profile_title": "حسابي",
  "profile_name": "الاسم الكامل",
  "profile_email": "الإيميل",
  "profile_phone": "التيليفون",
  "profile_password": "كلمة السر",
  "profile_current_password": "كلمة السر الحالية",
  "profile_new_password": "كلمة السر الجديدة",
  "profile_confirm_password": "تأكيد كلمة السر",
  "profile_save": "سجّل التغييرات",
  "profile_2fa": "التحقق بخطوتين",
  "profile_2fa_enable": "فعّل",

  "notifications_title": "الإشعارات",
  "notifications_none": "ما كاينش إشعارات جديدة",
  "notifications_mark_all_read": "علّم الكل كمقروء",
  "notifications_view_all": "شوف الكل"
}
```

---

## ÉTAPE 5 — I18nContext.jsx : ajouter l'anglais + hook complet

**Remplacer complètement :**
`edugestdz/frontend/src/context/I18nContext.jsx`

```jsx
import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import fr from '../lang/fr.json';
import ar from '../lang/ar.json';
import en from '../lang/en.json';
import dz from '../lang/dz.json';

const LANGUAGES = { fr, ar, en, dz };
const RTL_LANGS  = ['ar', 'dz'];

// Métadonnées des langues (pour le sélecteur)
export const LANG_META = {
  fr: { label: 'Français',  flag: '🇫🇷', dir: 'ltr' },
  ar: { label: 'العربية',  flag: '🇩🇿', dir: 'rtl' },
  en: { label: 'English',  flag: '🇬🇧', dir: 'ltr' },
  dz: { label: 'الدارجة', flag: '🇩🇿', dir: 'rtl' },
};

const I18nContext = createContext(null);

export function I18nProvider({ children }) {
  const [lang, setLang] = useState(() => {
    return localStorage.getItem('lang') || 'fr';
  });

  // Appliquer la direction et la langue dès le montage
  useEffect(() => {
    const dir = RTL_LANGS.includes(lang) ? 'rtl' : 'ltr';
    document.documentElement.dir  = dir;
    document.documentElement.lang = lang;
  }, [lang]);

  // Fonction de traduction avec interpolation de paramètres
  const t = useCallback((key, params = {}) => {
    const translations = LANGUAGES[lang] || LANGUAGES.fr;
    let text = translations[key] || LANGUAGES.fr[key] || key; // fallback fr puis clé brute
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, String(v));
    });
    return text;
  }, [lang]);

  const changeLang = useCallback((newLang) => {
    if (!LANGUAGES[newLang]) return;
    setLang(newLang);
    localStorage.setItem('lang', newLang);
    const dir = RTL_LANGS.includes(newLang) ? 'rtl' : 'ltr';
    document.documentElement.dir  = dir;
    document.documentElement.lang = newLang;
  }, []);

  const isRTL = RTL_LANGS.includes(lang);

  return (
    <I18nContext.Provider value={{ lang, t, changeLang, isRTL, LANG_META }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within <I18nProvider>');
  return ctx;
}
```

---

## ÉTAPE 6 — ThemeContext.jsx : dark/light mode

**Créer :**
`edugestdz/frontend/src/context/ThemeContext.jsx`

```jsx
import React, { createContext, useContext, useState, useEffect } from 'react';

const ThemeContext = createContext(null);

// ── Tokens de design par thème ────────────────────────────────────────────
const THEMES = {
  dark: {
    '--eg-bg':       '#070B14',
    '--eg-surface':  '#0D1117',
    '--eg-surface2': '#161C26',
    '--eg-border':   '#1E2D40',
    '--eg-text':     '#E2E8F0',
    '--eg-text2':    '#94A3B8',
    '--eg-muted':    '#64748B',
    '--eg-input-bg': '#161C26',
    '--eg-card-bg':  '#0D1117',
    '--eg-nav-bg':   '#0D1117',
    '--eg-topbar-bg':'#0D1117',
    '--eg-hover':    '#161C2688',
    '--eg-shadow':   '0 4px 24px rgba(0,0,0,0.5)',
  },
  light: {
    '--eg-bg':       '#F8FAFC',
    '--eg-surface':  '#FFFFFF',
    '--eg-surface2': '#F1F5F9',
    '--eg-border':   '#E2E8F0',
    '--eg-text':     '#0F172A',
    '--eg-text2':    '#475569',
    '--eg-muted':    '#94A3B8',
    '--eg-input-bg': '#F8FAFC',
    '--eg-card-bg':  '#FFFFFF',
    '--eg-nav-bg':   '#FFFFFF',
    '--eg-topbar-bg':'#FFFFFF',
    '--eg-hover':    '#F1F5F988',
    '--eg-shadow':   '0 2px 12px rgba(0,0,0,0.08)',
  },
};

function applyTheme(theme) {
  const root = document.documentElement;
  const tokens = THEMES[theme] || THEMES.dark;
  Object.entries(tokens).forEach(([prop, val]) => {
    root.style.setProperty(prop, val);
  });
  root.setAttribute('data-theme', theme);
  // Pour Tailwind darkMode: 'class'
  if (theme === 'dark') {
    root.classList.add('dark');
  } else {
    root.classList.remove('dark');
  }
}

export function ThemeProvider({ children }) {
  const [theme, setTheme] = useState(() => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') return saved;
    // Respecter la préférence système
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  });

  useEffect(() => {
    applyTheme(theme);
    localStorage.setItem('theme', theme);
  }, [theme]);

  // Écouter les changements de préférence système
  useEffect(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (e) => {
      if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light');
      }
    };
    mq.addEventListener('change', handler);
    return () => mq.removeEventListener('change', handler);
  }, []);

  const toggleTheme = () => setTheme(t => t === 'dark' ? 'light' : 'dark');
  const isDark = theme === 'dark';

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme, isDark, setTheme }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within <ThemeProvider>');
  return ctx;
}
```

---

## ÉTAPE 7 — Anti-flash : script dans index.html

**Modifier :**
`edugestdz/frontend/index.html`

Ajouter ce script dans `<head>` **avant** tout autre script — il s'exécute de manière synchrone pour éviter le flash blanc au chargement :

```html
<!-- Anti-flash theme script — doit être le PREMIER script dans <head> -->
<script>
  (function() {
    try {
      var saved = localStorage.getItem('theme');
      var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      var theme = saved || (prefersDark ? 'dark' : 'light');
      var root = document.documentElement;
      root.setAttribute('data-theme', theme);
      if (theme === 'dark') {
        root.classList.add('dark');
        root.style.setProperty('--eg-bg', '#070B14');
        root.style.setProperty('--eg-surface', '#0D1117');
        root.style.setProperty('--eg-text', '#E2E8F0');
        root.style.background = '#070B14';
      } else {
        root.style.setProperty('--eg-bg', '#F8FAFC');
        root.style.setProperty('--eg-surface', '#FFFFFF');
        root.style.setProperty('--eg-text', '#0F172A');
        root.style.background = '#F8FAFC';
      }
      // Langue et direction
      var lang = localStorage.getItem('lang') || 'fr';
      root.lang = lang;
      root.dir  = (lang === 'ar' || lang === 'dz') ? 'rtl' : 'ltr';
    } catch(e) {}
  })();
</script>
```

---

## ÉTAPE 8 — Ajouter ThemeProvider dans App.jsx

**Modifier :**
`edugestdz/frontend/src/App.jsx`

Ajouter l'import en haut :
```jsx
import { ThemeProvider } from '@context/ThemeContext';
```

Entourer le contenu existant avec `<ThemeProvider>` :

```jsx
// AVANT :
return (
  <BrowserRouter>
    <I18nProvider>
      <AuthProvider>
        ...
      </AuthProvider>
    </I18nProvider>
  </BrowserRouter>
);

// APRÈS :
return (
  <BrowserRouter>
    <ThemeProvider>
      <I18nProvider>
        <AuthProvider>
          ...
        </AuthProvider>
      </I18nProvider>
    </ThemeProvider>
  </BrowserRouter>
);
```

---

## ÉTAPE 9 — Mettre à jour index.css avec les variables thème

**Modifier :**
`edugestdz/frontend/src/index.css`

Ajouter après les imports Tailwind existants :

```css
/* ── Variables thème (dark par défaut — surchargées par ThemeContext) ── */
:root {
  --eg-bg:        #070B14;
  --eg-surface:   #0D1117;
  --eg-surface2:  #161C26;
  --eg-border:    #1E2D40;
  --eg-text:      #E2E8F0;
  --eg-text2:     #94A3B8;
  --eg-muted:     #64748B;
  --eg-input-bg:  #161C26;
  --eg-card-bg:   #0D1117;
  --eg-nav-bg:    #0D1117;
  --eg-topbar-bg: #0D1117;
  --eg-hover:     #161C2688;
  --eg-shadow:    0 4px 24px rgba(0,0,0,0.5);

  /* Accents — fixes dans les deux thèmes */
  --eg-blue:      #2563EB;
  --eg-blue-light:#93C5FD;
  --eg-green:     #10B981;
  --eg-orange:    #F59E0B;
  --eg-red:       #EF4444;
  --eg-purple:    #7C3AED;
  --eg-teal:      #06B6D4;
}

/* Thème clair — surchargé dynamiquement par ThemeContext */
[data-theme="light"] {
  --eg-bg:        #F8FAFC;
  --eg-surface:   #FFFFFF;
  --eg-surface2:  #F1F5F9;
  --eg-border:    #E2E8F0;
  --eg-text:      #0F172A;
  --eg-text2:     #475569;
  --eg-muted:     #94A3B8;
  --eg-input-bg:  #F8FAFC;
  --eg-card-bg:   #FFFFFF;
  --eg-nav-bg:    #FFFFFF;
  --eg-topbar-bg: #FFFFFF;
  --eg-hover:     #F1F5F988;
  --eg-shadow:    0 2px 12px rgba(0,0,0,0.08);
}

/* Application globale des variables */
body {
  background-color: var(--eg-bg);
  color: var(--eg-text);
  transition: background-color 0.2s ease, color 0.2s ease;
}

/* RTL support */
[dir="rtl"] .sidebar-item-text { text-align: right; }
[dir="rtl"] .breadcrumb        { flex-direction: row-reverse; }
```

---

## ÉTAPE 10 — Composant LanguageThemeSelector

**Créer :**
`edugestdz/frontend/src/components/LanguageThemeSelector.jsx`

```jsx
import { useState } from 'react';
import { useI18n, LANG_META } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';

/**
 * LanguageThemeSelector
 * Boutons pour changer la langue et le thème
 * À placer dans le Header
 */
export default function LanguageThemeSelector({ compact = false }) {
  const { lang, changeLang, t } = useI18n();
  const { isDark, toggleTheme }  = useTheme();
  const [showLangMenu, setShowLangMenu] = useState(false);

  const currentLang = LANG_META[lang] || LANG_META.fr;

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', position: 'relative' }}>

      {/* ── Theme toggle ── */}
      <button
        onClick={toggleTheme}
        title={isDark ? t('theme_light') : t('theme_dark')}
        style={{
          width: '36px', height: '36px',
          background: 'var(--eg-surface2)',
          border: '1px solid var(--eg-border)',
          borderRadius: '9px',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          cursor: 'pointer', fontSize: '16px',
          transition: 'all 0.15s',
          color: 'var(--eg-text)',
        }}
        onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--eg-blue)'}
        onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--eg-border)'}
      >
        {isDark ? '☀️' : '🌙'}
      </button>

      {/* ── Language selector ── */}
      <div style={{ position: 'relative' }}>
        <button
          onClick={() => setShowLangMenu(!showLangMenu)}
          title={t('lang_select')}
          style={{
            height: '36px',
            background: 'var(--eg-surface2)',
            border: '1px solid var(--eg-border)',
            borderRadius: '9px',
            display: 'flex', alignItems: 'center',
            gap: '6px', padding: '0 10px',
            cursor: 'pointer', fontSize: '12px',
            fontWeight: 700,
            color: 'var(--eg-text)',
            fontFamily: 'Inter, sans-serif',
            transition: 'all 0.15s',
          }}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--eg-blue)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--eg-border)'}
        >
          <span>{currentLang.flag}</span>
          {!compact && <span>{currentLang.label}</span>}
          <span style={{ fontSize: '10px', color: 'var(--eg-muted)' }}>▾</span>
        </button>

        {/* Dropdown */}
        {showLangMenu && (
          <>
            {/* Overlay pour fermer */}
            <div
              style={{ position: 'fixed', inset: 0, zIndex: 99 }}
              onClick={() => setShowLangMenu(false)}
            />
            <div style={{
              position: 'absolute',
              top: '42px',
              right: 0,
              background: 'var(--eg-surface)',
              border: '1px solid var(--eg-border)',
              borderRadius: '12px',
              boxShadow: 'var(--eg-shadow)',
              zIndex: 100,
              overflow: 'hidden',
              minWidth: '160px',
            }}>
              {Object.entries(LANG_META).map(([code, meta]) => (
                <button
                  key={code}
                  onClick={() => { changeLang(code); setShowLangMenu(false); }}
                  style={{
                    width: '100%',
                    display: 'flex', alignItems: 'center', gap: '10px',
                    padding: '10px 14px',
                    background: lang === code ? 'var(--eg-blue)18' : 'transparent',
                    border: 'none',
                    borderBottom: '1px solid var(--eg-border)',
                    cursor: 'pointer',
                    fontSize: '12px', fontWeight: lang === code ? 700 : 500,
                    color: lang === code ? 'var(--eg-blue-light)' : 'var(--eg-text)',
                    textAlign: 'left',
                    fontFamily: 'Inter, sans-serif',
                    direction: meta.dir,
                    transition: 'background 0.1s',
                  }}
                  onMouseEnter={e => { if (lang !== code) e.currentTarget.style.background = 'var(--eg-hover)'; }}
                  onMouseLeave={e => { if (lang !== code) e.currentTarget.style.background = 'transparent'; }}
                >
                  <span style={{ fontSize: '18px' }}>{meta.flag}</span>
                  <span>{meta.label}</span>
                  {lang === code && (
                    <span style={{ marginLeft: 'auto', fontSize: '12px', color: 'var(--eg-green)' }}>✓</span>
                  )}
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
```

---

## ÉTAPE 11 — Mettre à jour Header.jsx pour intégrer le sélecteur

**Modifier :**
`edugestdz/frontend/src/components/Header.jsx`

Ajouter l'import :
```jsx
import LanguageThemeSelector from '@components/LanguageThemeSelector';
```

Dans le JSX du Header, remplacer les boutons ⚙️ et ajouter le sélecteur :

```jsx
{/* Remplacer la zone des boutons de droite par : */}

{/* ── Lang + Theme ── */}
<LanguageThemeSelector />

{/* ── Notifications ── */}
{/* ... garder le code notifications existant ... */}

{/* ── Settings (lien profil) ── */}
<Link to="/profil" style={{...}}>⚙️</Link>
```

---

## ÉTAPE 12 — Mettre à jour Sidebar.jsx pour utiliser t()

**Modifier :**
`edugestdz/frontend/src/components/Sidebar.jsx`

Ajouter les imports :
```jsx
import { useI18n } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';
```

Dans le composant, récupérer `t` et l'utiliser pour les labels :
```jsx
const { t, isRTL } = useI18n();
const { isDark }   = useTheme();

// Remplacer dans NAV_SECTIONS les label hardcodés par des clés :
// label: 'Tableau de bord'  →  label: t('nav_dashboard')
// label: 'Élèves'          →  label: t('nav_students')
// etc.
// ET les section labels :
// 'Principal'  → t('section_main')
// 'Pédagogie'  → t('section_pedagogy')
// etc.
```

Utiliser `isDark` pour adapter les couleurs si nécessaire :
```jsx
// Fond sidebar selon thème
style={{
  background: 'var(--eg-nav-bg)',
  borderRight: `1px solid var(--eg-border)`,
  ...
}}
```

---

## ÉTAPE 13 — Mettre à jour LoginPage.jsx pour i18n + thème

**Modifier :**
`edugestdz/frontend/src/pages/LoginPage.jsx`

Ajouter :
```jsx
import { useI18n } from '@context/I18nContext';
import { useTheme } from '@context/ThemeContext';
import LanguageThemeSelector from '@components/LanguageThemeSelector';

// Dans le composant :
const { t } = useI18n();
const { isDark } = useTheme();

// Utiliser t() pour les textes :
// placeholder="admin@edugest.dz" → garder en dur (exemple)
// "Se connecter" → {t('login_submit')}
// "Adresse email" → {t('login_email')}
// "Mot de passe" → {t('login_password')}
// "Erreur réseau..." → {t('error_network')}
// "EduGest DZ" → {t('app_name')}
// "Plateforme de gestion scolaire" → {t('login_subtitle')}

// Adapter les couleurs avec les variables CSS :
// background: '#08090f' → background: 'var(--eg-bg)'
// background: '#111318' → background: 'var(--eg-surface)'
// color: '#e2e8f0'      → color: 'var(--eg-text)'
// border: '1px solid #1e293b' → border: '1px solid var(--eg-border)'

// Ajouter le sélecteur lang/theme en haut à droite de la page login :
<div style={{ position: 'absolute', top: '20px', right: '20px' }}>
  <LanguageThemeSelector compact />
</div>
```

---

## ÉTAPE 14 — Exporter depuis le contexte index

**Créer :**
`edugestdz/frontend/src/context/index.js`

```js
export { I18nProvider, useI18n, LANG_META } from './I18nContext';
export { ThemeProvider, useTheme }          from './ThemeContext';
export { AuthProvider, useAuth }            from './AuthContext';
```

---

## ÉTAPE 15 — tailwind.config.js : activer darkMode class

**Modifier :**
`edugestdz/frontend/tailwind.config.js`

```js
export default {
  darkMode: 'class',  // ← AJOUTER cette ligne
  content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      // ... garder l'existant
    },
  },
  plugins: [],
}
```

---

## ÉTAPE 16 — Build et commit

```bash
cd edugestdz/frontend

# Vérifier les imports manquants
npm run build 2>&1 | grep -i "error\|cannot find\|module"

# Si erreur "Cannot find module '../lang/en.json'" → vérifier le fichier existe
# Si erreur "LANG_META is not exported" → vérifier l'export dans I18nContext.jsx
# Si erreur de CSS → vérifier les variables --eg-* dans index.css

# Build complet
npm run build
# Attendu : ✓ built in Xs — 0 errors

# Commit
git add \
  edugestdz/frontend/src/lang/fr.json \
  edugestdz/frontend/src/lang/ar.json \
  edugestdz/frontend/src/lang/en.json \
  edugestdz/frontend/src/lang/dz.json \
  edugestdz/frontend/src/context/I18nContext.jsx \
  edugestdz/frontend/src/context/ThemeContext.jsx \
  edugestdz/frontend/src/context/index.js \
  edugestdz/frontend/src/components/LanguageThemeSelector.jsx \
  edugestdz/frontend/src/components/Header.jsx \
  edugestdz/frontend/src/components/Sidebar.jsx \
  edugestdz/frontend/src/pages/LoginPage.jsx \
  edugestdz/frontend/src/App.jsx \
  edugestdz/frontend/src/index.css \
  edugestdz/frontend/tailwind.config.js \
  edugestdz/frontend/index.html

git commit -m "feat(i18n+theme): Dark/Light mode + 4 langues (FR·AR·EN·Darija DZ) — ThemeContext, I18nContext enrichi, anti-flash, LanguageThemeSelector"
git push origin develop
# → PR develop → main
```

---

## RÉSUMÉ COMPLET

| Fichier | Action | Résultat |
|---|---|---|
| `lang/fr.json` | 120+ clés complètes | Toute l'interface en français |
| `lang/en.json` | NOUVEAU · 120+ clés | Interface en anglais |
| `lang/ar.json` | 120+ clés complètes | Interface en arabe MSA |
| `lang/dz.json` | ENRICHI · Darija DZ | Interface en dialecte algérien oranais |
| `I18nContext.jsx` | Ajout `en` + `isRTL` + `LANG_META` | Hook complet + fallback fr |
| `ThemeContext.jsx` | NOUVEAU | Dark/Light + CSS variables + système |
| `index.html` | Script anti-flash | Pas de clignotement blanc au chargement |
| `index.css` | Variables `--eg-*` pour les 2 thèmes | Transitions fluides |
| `tailwind.config.js` | `darkMode: 'class'` | Tailwind dark classes actives |
| `LanguageThemeSelector.jsx` | NOUVEAU composant | Dropdown langue + toggle thème dans Header/Login |
| `App.jsx` | Ajouter `<ThemeProvider>` | Thème disponible partout |
| `Header.jsx` | Intégrer `LanguageThemeSelector` | Boutons visibles en haut |
| `Sidebar.jsx` | Utiliser `t()` + `var(--eg-*)` | Labels traduits + couleurs thème |
| `LoginPage.jsx` | `t()` + `var(--eg-*)` + sélecteur | Page login multilingue + thème |

---

## NOTE SUR LA DARIJA ALGÉRIENNE

> **Recherche effectuée** : Il n'existe pas de package npm officiel pour la Darija algérienne.
> Les ressources trouvées (DzLingo, Parler-Algerien.com, dictionnaires Glosbe) sont des sites
> d'apprentissage, pas des fichiers i18n prêts à l'emploi.
>
> **Notre approche** : Traductions manuelles en dialecte oranais/algérois courant,
> écriture arabe (pas Arabizi), direction RTL identique à l'arabe MSA.
> Les termes techniques (CIB, Dahabia, SMS, Dashboard) sont gardés en français/anglais
> car c'est l'usage réel en Algérie.
>
> **Code langue** : `dz` (non standard ISO 639, mais cohérent avec l'existant du projet).
>
> **Exemples de traductions darija utilisées** :
> - "Enregistrer" → "سجّل" (sajjil)
> - "Élèves" → "التلامذة" (avec hamza, usage algérien)
> - "Impayés" → "اللي ما دفعوش" (lli ma dafʕouch)
> - "Bus" → "الكار" (le mot "car" arabisé, usage courant à Oran)
> - "Cantine" → "الكانطين" (transcription phonétique du français)
> - "Absent" → "غائب" ou "ما جاش" selon le contexte

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_THEME_I18N_COMPLET.md — 16 étapes dans l'ordre.

RÈGLES ABSOLUES :
1. Ne jamais supprimer les routes dans App.jsx.
2. Ne jamais supprimer les imports de pages dans App.jsx.
3. AuthContext.jsx ne doit pas être modifié.
4. L'alias @context/ et @components/ sont déjà configurés dans vite.config.js — ne pas changer.
5. Après chaque étape majeure : npm run build → vérifier 0 erreur avant de continuer.
6. Si erreur "Cannot find module" pour les JSON → vérifier que le fichier existe dans src/lang/.
7. Si erreur "LANG_META is not exported" → vérifier l'export nommé dans I18nContext.jsx.
8. Le script anti-flash dans index.html doit être le PREMIER script dans <head>.
9. ThemeProvider entoure tout dans App.jsx, AVANT I18nProvider et AuthProvider.

npm run build → 0 erreur
git push origin develop → PR develop → main
```
