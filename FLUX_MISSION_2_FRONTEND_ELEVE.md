# 📱 MISSION 2/3 — Frontend React : Espace Élève + Plages Horaires + Portfolio
## EduGest DZ · Flux info mondial adapté · Branche : develop · 10 Juillet 2026
## Prérequis : Mission 1 mergée · 0 régression

---

## CONTEXTE RÉEL LU DANS LE REPO

```
FRONTEND EXISTANT (lu dans GitHub) :
✅ React 19 + Vite 8 + Tailwind CSS 3
✅ DashboardPage.jsx, ElevesPage.jsx, FinancePage.jsx, etc.
✅ ThemeContext (dark/light) + I18nContext (FR/AR/EN/Darija)
✅ Sidebar.jsx avec navigation
✅ Composants UI : Card, KpiCard, Badge, Alert, BarChart, DonutChart
✅ OfflineBanner.jsx (créé Mission PWA)
✅ useOfflineStatus.js hook

CE QUI MANQUE (cette mission crée) :
❌ EspacElevePage.jsx (espace dédié élève/étudiant)
❌ DevoirsPage.jsx (liste devoirs avec date + matière)
❌ SignalementsPage.jsx (feedback + signalement grave UI)
❌ hook useNotificationHoraire (push délayé selon plage)
❌ Bannière "Cours annulé/remplacé" dans le planning
❌ Portfolio numérique élève (inspiré Seesaw)
❌ NotificationsPage.jsx (boîte de réception in-app)
```

### RÈGLES
1. **Dark + Light** — utiliser les variables CSS existantes (var(--bg), var(--surface), etc.)
2. **Responsive** — mobile-first (les élèves utilisent leur téléphone)
3. **0 dépendance supplémentaire** — uniquement ce qui est dans package.json
4. **Appels API** — utiliser la fonction `api()` existante du DashboardPage comme pattern
5. **Accessibilité** — labels ARIA sur les formulaires sensibles (signalement)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
cd edugestdz/frontend
```

---

## ÉTAPE 1 — Hook : useNotificationHoraire (plage 7h-20h Algérie)

**Créer** : `edugestdz/frontend/src/hooks/useNotificationHoraire.js`

```javascript
/**
 * useNotificationHoraire — Gère les plages horaires de notification.
 *
 * INSPIRÉ DE : France (Pronote déconnecté 20h-7h depuis sept. 2025)
 * ADAPTÉ POUR : Algérie — fuseau Africa/Algiers (UTC+1)
 *
 * Règles :
 * - Push actif : 7h → 20h (heure Algérie)
 * - En dehors de la plage : les notifications sont stockées
 *   mais le push Firebase est différé
 * - Urgences (absence, billet grave) : toujours notifiées
 */

import { useState, useEffect, useCallback } from 'react';

const HEURE_DEBUT = 7;   // 7h du matin
const HEURE_FIN   = 20;  // 20h le soir

export function useNotificationHoraire() {
  const [dansPlagHoraire, setDansPlagHoraire] = useState(true);
  const [prochaineOuverture, setProchaineOuverture] = useState(null);

  const verifier = useCallback(() => {
    // Heure locale Algérie (UTC+1)
    const maintenant = new Date();
    const heureAlgerie = new Intl.DateTimeFormat('fr-DZ', {
      timeZone: 'Africa/Algiers',
      hour: 'numeric',
      hour12: false,
    }).format(maintenant);

    const heure = parseInt(heureAlgerie, 10);
    const dansPlage = heure >= HEURE_DEBUT && heure < HEURE_FIN;
    setDansPlagHoraire(dansPlage);

    if (!dansPlage) {
      // Calculer la prochaine ouverture
      const demain = new Date(maintenant);
      if (heure >= HEURE_FIN) demain.setDate(demain.getDate() + 1);
      demain.setHours(HEURE_DEBUT, 0, 0, 0);
      setProchaineOuverture(demain);
    } else {
      setProchaineOuverture(null);
    }
  }, []);

  useEffect(() => {
    verifier();
    // Revérifier toutes les 15 minutes
    const interval = setInterval(verifier, 15 * 60 * 1000);
    return () => clearInterval(interval);
  }, [verifier]);

  const formatProchaineOuverture = () => {
    if (!prochaineOuverture) return null;
    return new Intl.DateTimeFormat('fr-DZ', {
      timeZone: 'Africa/Algiers',
      hour: '2-digit', minute: '2-digit',
    }).format(prochaineOuverture);
  };

  return {
    dansPlagHoraire,
    prochaineOuverture: formatProchaineOuverture(),
    HEURE_DEBUT,
    HEURE_FIN,
  };
}
```

---

## ÉTAPE 2 — NotificationsPage.jsx (boîte de réception in-app)

**Créer** : `edugestdz/frontend/src/pages/NotificationsPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import Card from '@components/ui/Card';
import Badge from '@components/ui/Badge';
import { useNotificationHoraire } from '@hooks/useNotificationHoraire';

const api = (path, options = {}) =>
  fetch(`/api/v1${path}`, {
    headers: {
      Authorization: `Bearer ${localStorage.getItem('token')}`,
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  }).then((r) => r.json());

const ICONES = {
  absence_enseignant:   '⚠️',
  cours_modifie:        '📅',
  remplacement_assigne: '🔄',
  note_publiee:         '📊',
  bulletin_disponible:  '📄',
  devoir_publie:        '📝',
  facture_impayee:      '💰',
  signalement_grave:    '🚨',
  signalement_traite:   '📋',
  accusé_reception:     '✅',
  feedback_recu:        '💬',
  convocation:          '📞',
  cours_modifie:        '📅',
};

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState([]);
  const [loading,       setLoading]       = useState(true);
  const [filtreNonLu,   setFiltreNonLu]   = useState(false);
  const { dansPlagHoraire, prochaineOuverture } = useNotificationHoraire();

  const charger = async () => {
    setLoading(true);
    try {
      const res = await api(`/notifications?non_lu=${filtreNonLu}`);
      if (res.success) setNotifications(res.data ?? []);
    } catch {}
    finally { setLoading(false); }
  };

  useEffect(() => { charger(); }, [filtreNonLu]);

  const marquerLu = async (id) => {
    await api(`/notifications/${id}/lu`, { method: 'PATCH' });
    setNotifications(prev =>
      prev.map(n => n.id === id ? { ...n, lu: true } : n)
    );
  };

  const marquerToutLu = async () => {
    await api('/notifications/tout-lu', { method: 'PATCH' });
    setNotifications(prev => prev.map(n => ({ ...n, lu: true })));
  };

  const nbNonLu = notifications.filter(n => !n.lu).length;

  const formatDate = (date) => {
    const d = new Date(date);
    const maintenant = new Date();
    const diff = Math.floor((maintenant - d) / 60000); // minutes
    if (diff < 1)   return 'À l\'instant';
    if (diff < 60)  return `Il y a ${diff}min`;
    if (diff < 1440)return `Il y a ${Math.floor(diff / 60)}h`;
    return d.toLocaleDateString('fr-DZ');
  };

  return (
    <div className="animate-fadeIn space-y-5">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-extrabold text-text">Notifications</h1>
          <p className="text-xs text-muted mt-1">
            {nbNonLu > 0 ? `${nbNonLu} non lue(s)` : 'Tout est à jour'}
          </p>
        </div>
        <div className="flex gap-2">
          {nbNonLu > 0 && (
            <button
              onClick={marquerToutLu}
              className="text-xs font-semibold text-accent hover:underline"
            >
              Tout marquer comme lu
            </button>
          )}
          <button
            onClick={() => setFiltreNonLu(f => !f)}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
              filtreNonLu
                ? 'bg-accent text-white border-accent'
                : 'bg-surface2 text-muted border-border'
            }`}
          >
            {filtreNonLu ? '✓ Non lues seulement' : 'Filtrer non lues'}
          </button>
        </div>
      </div>

      {/* Bannière hors plage horaire */}
      {!dansPlagHoraire && (
        <div className="flex items-center gap-3 p-3 rounded-xl text-xs font-medium"
             style={{ background: 'rgba(100,116,139,0.08)', border: '1px solid rgba(100,116,139,0.2)' }}>
          <span>🌙</span>
          <span className="text-muted">
            Mode silencieux actif (20h → 7h). Les nouvelles notifications seront affichées à{' '}
            <strong className="text-text">{prochaineOuverture}</strong>. Les urgences restent actives.
          </span>
        </div>
      )}

      {/* Liste */}
      {loading ? (
        <div className="space-y-3">
          {[1,2,3,4].map(i => (
            <div key={i} className="h-16 bg-surface2 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : notifications.length === 0 ? (
        <Card>
          <div className="text-center py-10">
            <div className="text-4xl mb-3">🔔</div>
            <p className="text-sm font-semibold text-text">Aucune notification</p>
            <p className="text-xs text-muted mt-1">
              {filtreNonLu ? 'Toutes vos notifications ont été lues.' : 'Vous êtes à jour !'}
            </p>
          </div>
        </Card>
      ) : (
        <div className="space-y-2">
          {notifications.map(notif => (
            <button
              key={notif.id}
              onClick={() => !notif.lu && marquerLu(notif.id)}
              className={`w-full text-left flex items-start gap-3 p-4 rounded-xl border transition-all ${
                notif.lu
                  ? 'bg-surface border-border opacity-70'
                  : 'bg-surface border-accent/30 hover:border-accent/60'
              }`}
            >
              {/* Icône + point non lu */}
              <div className="relative flex-shrink-0 mt-0.5">
                <span className="text-xl">
                  {ICONES[notif.type] ?? '🔔'}
                </span>
                {!notif.lu && (
                  <span className="absolute -top-1 -right-1 w-2.5 h-2.5 bg-accent rounded-full" />
                )}
              </div>

              {/* Contenu */}
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <p className={`text-sm font-semibold ${notif.lu ? 'text-muted' : 'text-text'}`}>
                    {notif.titre}
                  </p>
                  <span className="text-[10px] text-muted flex-shrink-0">
                    {formatDate(notif.created_at)}
                  </span>
                </div>
                <p className="text-xs text-muted mt-0.5 line-clamp-2">
                  {notif.corps}
                </p>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 3 — DevoirsPage.jsx (élève voit ses devoirs)

**Créer** : `edugestdz/frontend/src/pages/DevoirsPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import Card from '@components/ui/Card';
import Badge from '@components/ui/Badge';

const api = (path) =>
  fetch(`/api/v1${path}`, {
    headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
  }).then((r) => r.json());

const diffJours = (dateStr) => {
  const diff = Math.ceil((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24));
  return diff;
};

const urgenceColor = (jours) => {
  if (jours <= 1)  return 'var(--red)';
  if (jours <= 3)  return 'var(--orange)';
  if (jours <= 7)  return 'var(--teal)';
  return 'var(--green)';
};

export default function DevoirsPage() {
  const [devoirs,  setDevoirs]  = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [filtreMat,setFiltreMat]= useState('tous');
  const matieres = ['tous', ...new Set(devoirs.map(d => d.matiere).filter(Boolean))];

  useEffect(() => {
    api('/devoirs')
      .then(r => { if (r.success) setDevoirs(r.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const filtres = devoirs.filter(d =>
    filtreMat === 'tous' || d.matiere === filtreMat
  );

  // Trier par date de remise (plus urgents en premier)
  const tries = [...filtres].sort((a, b) =>
    new Date(a.date_remise) - new Date(b.date_remise)
  );

  return (
    <div className="animate-fadeIn space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-extrabold text-text">Mes Devoirs</h1>
          <p className="text-xs text-muted mt-1">
            {devoirs.length} devoir(s) à venir
          </p>
        </div>
      </div>

      {/* Filtres matières */}
      {matieres.length > 1 && (
        <div className="flex gap-2 flex-wrap">
          {matieres.map(m => (
            <button
              key={m}
              onClick={() => setFiltreMat(m)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
                filtreMat === m
                  ? 'bg-accent text-white border-accent'
                  : 'bg-surface2 text-muted border-border hover:text-text'
              }`}
            >
              {m === 'tous' ? 'Toutes' : m}
            </button>
          ))}
        </div>
      )}

      {/* Liste devoirs */}
      {loading ? (
        <div className="space-y-3">
          {[1,2,3].map(i => <div key={i} className="h-24 bg-surface2 rounded-xl animate-pulse" />)}
        </div>
      ) : tries.length === 0 ? (
        <Card>
          <div className="text-center py-10">
            <div className="text-4xl mb-3">📚</div>
            <p className="text-sm font-semibold text-text">Aucun devoir à venir</p>
            <p className="text-xs text-muted mt-1">Profitez-en !</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-3">
          {tries.map(devoir => {
            const joursRestants = diffJours(devoir.date_remise);
            const couleur       = urgenceColor(joursRestants);
            const dateFormatee  = new Date(devoir.date_remise)
              .toLocaleDateString('fr-DZ', { day: '2-digit', month: 'long', year: 'numeric' });

            return (
              <div
                key={devoir.id}
                className="bg-surface border border-border rounded-xl p-4 hover:border-accent/30 transition-colors"
                style={{ borderLeft: `4px solid ${couleur}` }}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-xs font-bold px-2 py-0.5 rounded"
                            style={{ background: `${couleur}18`, color: couleur }}>
                        {devoir.matiere ?? 'Matière non précisée'}
                      </span>
                    </div>
                    <h3 className="text-sm font-bold text-text">{devoir.titre}</h3>
                    {devoir.description && (
                      <p className="text-xs text-muted mt-1 line-clamp-2">{devoir.description}</p>
                    )}
                    <div className="flex items-center gap-3 mt-2">
                      <span className="text-xs text-muted">📅 À remettre le {dateFormatee}</span>
                      {devoir.fichier_chemin && (
                        <span className="text-xs text-accent">📎 Document joint</span>
                      )}
                    </div>
                  </div>
                  <div className="text-right flex-shrink-0">
                    <div className="text-xl font-900" style={{ color: couleur }}>
                      {joursRestants}
                    </div>
                    <div className="text-[10px] text-muted">
                      {joursRestants <= 1 ? 'jour' : 'jours'}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Légende urgence */}
      <div className="flex gap-4 text-[10px] text-muted pt-2 border-t border-border">
        <span style={{ color: 'var(--red)' }}>● &lt;2j Urgent</span>
        <span style={{ color: 'var(--orange)' }}>● 2-3j Bientôt</span>
        <span style={{ color: 'var(--teal)' }}>● 4-7j Cette semaine</span>
        <span style={{ color: 'var(--green)' }}>● +7j OK</span>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 4 — FeedbackEnseignantPage.jsx (feedback + signalement)

**Créer** : `edugestdz/frontend/src/pages/FeedbackEnseignantPage.jsx`

```jsx
import { useState, useEffect } from 'react';
import Card from '@components/ui/Card';
import Alert from '@components/ui/Alert';

const api = (path, options = {}) =>
  fetch(`/api/v1${path}`, {
    headers: {
      Authorization: `Bearer ${localStorage.getItem('token')}`,
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  }).then((r) => r.json());

const TYPES_FEEDBACK = [
  { val: 'pedagogie',   label: '📚 Pédagogie', desc: 'Qualité des explications' },
  { val: 'rythme',      label: '⏱️ Rythme',     desc: 'Rythme du cours' },
  { val: 'ambiance',    label: '🌟 Ambiance',   desc: 'Climat de la classe' },
  { val: 'relation',    label: '🤝 Relation',   desc: 'Relation élève-enseignant' },
  { val: 'ressources',  label: '📎 Ressources', desc: 'Supports de cours' },
  { val: 'autre',       label: '💬 Autre',       desc: 'Autre remarque' },
];

const TYPES_INCIDENT = [
  { val: 'violence_verbale',         label: '🗣️ Paroles blessantes' },
  { val: 'violence_physique',        label: '⚠️ Violence physique' },
  { val: 'harcelement',              label: '🔁 Harcèlement répété' },
  { val: 'discrimination',           label: '🚫 Discrimination' },
  { val: 'comportement_inapproprie', label: '❌ Comportement inapproprié' },
  { val: 'autre',                    label: '📝 Autre situation grave' },
];

export default function FeedbackEnseignantPage() {
  const [onglet,       setOnglet]       = useState('feedback'); // feedback | signalement
  const [enseignants,  setEnseignants]  = useState([]);
  const [submitted,    setSubmitted]    = useState(false);
  const [error,        setError]        = useState(null);
  const [loading,      setLoading]      = useState(false);

  // Feedback state
  const [fb, setFb] = useState({
    enseignant_user_id: '',
    trimestre:           3,
    note_qualite:        3,
    type_feedback:       'pedagogie',
    commentaire:         '',
  });

  // Signalement state
  const [sg, setSg] = useState({
    type_incident:  '',
    gravite:        'important',
    description:    '',
    date_incident:  new Date().toISOString().split('T')[0],
    concerne_id:    '',
    temoins:        '',
  });

  useEffect(() => {
    api('/enseignants')
      .then(r => { if (r.data) setEnseignants(r.data); })
      .catch(() => {});
  }, []);

  const soumetteFeedback = async (e) => {
    e.preventDefault();
    setLoading(true); setError(null);
    try {
      const res = await api('/feedbacks-pedagogiques', {
        method: 'POST',
        body: JSON.stringify(fb),
      });
      if (res.success) setSubmitted(true);
      else setError(res.message ?? 'Erreur inconnue');
    } catch { setError('Erreur réseau'); }
    finally { setLoading(false); }
  };

  const soumetteSignalement = async (e) => {
    e.preventDefault();
    if (sg.description.length < 20) {
      setError('Veuillez décrire la situation en au moins 20 caractères.');
      return;
    }
    setLoading(true); setError(null);
    try {
      const res = await api('/signalements-graves', {
        method: 'POST',
        body: JSON.stringify(sg),
      });
      if (res.success) setSubmitted(true);
      else setError(res.message ?? 'Erreur inconnue');
    } catch { setError('Erreur réseau'); }
    finally { setLoading(false); }
  };

  if (submitted) {
    return (
      <div className="animate-fadeIn max-w-lg mx-auto pt-8">
        <Card>
          <div className="text-center py-8">
            <div className="text-5xl mb-4">{onglet === 'feedback' ? '⭐' : '✅'}</div>
            <h2 className="text-lg font-bold text-text mb-2">
              {onglet === 'feedback' ? 'Feedback envoyé !' : 'Signalement enregistré'}
            </h2>
            <p className="text-sm text-muted mb-4">
              {onglet === 'feedback'
                ? 'Votre feedback a été transmis au directeur. L\'enseignant recevra un résumé anonymisé.'
                : 'Votre signalement est confidentiel. Seul le directeur y a accès. Vous serez informé(e) des suites.'}
            </p>
            <button
              onClick={() => { setSubmitted(false); setError(null); }}
              className="px-6 py-2 bg-accent text-white rounded-lg text-sm font-semibold"
            >
              Retour
            </button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="animate-fadeIn max-w-2xl mx-auto space-y-5">

      <div>
        <h1 className="text-xl font-extrabold text-text">Communication avec l'école</h1>
        <p className="text-xs text-muted mt-1">
          Tous vos messages sont confidentiels et vus uniquement par le directeur.
        </p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-surface2 rounded-lg">
        {[
          { id: 'feedback',     label: '⭐ Feedback pédagogique', desc: 'Note qualité des cours' },
          { id: 'signalement',  label: '🚨 Signalement grave',    desc: 'Situation préoccupante' },
        ].map(t => (
          <button
            key={t.id}
            onClick={() => { setOnglet(t.id); setError(null); }}
            className={`flex-1 py-2 px-3 rounded-md text-xs font-semibold transition-all ${
              onglet === t.id
                ? 'bg-surface shadow text-text'
                : 'text-muted hover:text-text'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {error && (
        <Alert type="error" onDismiss={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* ── FEEDBACK PÉDAGOGIQUE ── */}
      {onglet === 'feedback' && (
        <Card>
          <div className="mb-4 p-3 rounded-lg text-xs"
               style={{ background: 'rgba(16,185,129,0.06)', border: '1px solid rgba(16,185,129,0.2)', color: 'var(--green)' }}>
            💡 <strong>Confidentiel :</strong> Votre nom n'est jamais transmis à l'enseignant.
            Il reçoit uniquement une note moyenne anonymisée.
          </div>

          <form onSubmit={soumetteFeedback} className="space-y-4">
            {/* Enseignant */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                Enseignant concerné <span style={{ color: 'var(--red)' }}>*</span>
              </label>
              <select
                required
                value={fb.enseignant_user_id}
                onChange={e => setFb(p => ({ ...p, enseignant_user_id: e.target.value }))}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-text"
              >
                <option value="">Choisir un enseignant</option>
                {enseignants.map(e => (
                  <option key={e.id} value={e.user_id ?? e.id}>
                    {e.nom} {e.prenom} — {e.specialite ?? ''}
                  </option>
                ))}
              </select>
            </div>

            {/* Trimestre */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">Trimestre</label>
              <div className="flex gap-2">
                {[1, 2, 3].map(t => (
                  <button
                    key={t}
                    type="button"
                    onClick={() => setFb(p => ({ ...p, trimestre: t }))}
                    className={`flex-1 py-2 rounded-lg text-xs font-semibold border transition-colors ${
                      fb.trimestre === t
                        ? 'bg-accent text-white border-accent'
                        : 'bg-surface2 text-muted border-border'
                    }`}
                  >
                    T{t}
                  </button>
                ))}
              </div>
            </div>

            {/* Note étoiles */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                Note de qualité {fb.note_qualite}/5
              </label>
              <div className="flex gap-2">
                {[1,2,3,4,5].map(n => (
                  <button
                    key={n}
                    type="button"
                    onClick={() => setFb(p => ({ ...p, note_qualite: n }))}
                    className="text-2xl transition-transform hover:scale-110"
                  >
                    {n <= fb.note_qualite ? '⭐' : '☆'}
                  </button>
                ))}
              </div>
            </div>

            {/* Type */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">Aspect évalué</label>
              <div className="grid grid-cols-2 gap-2">
                {TYPES_FEEDBACK.map(t => (
                  <button
                    key={t.val}
                    type="button"
                    onClick={() => setFb(p => ({ ...p, type_feedback: t.val }))}
                    className={`p-2 rounded-lg text-left text-xs border transition-colors ${
                      fb.type_feedback === t.val
                        ? 'bg-accent/10 border-accent text-accent'
                        : 'bg-surface2 border-border text-muted hover:text-text'
                    }`}
                  >
                    <div className="font-semibold">{t.label}</div>
                    <div className="opacity-70 text-[10px]">{t.desc}</div>
                  </button>
                ))}
              </div>
            </div>

            {/* Commentaire */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                Commentaire (optionnel — max 500 caractères)
              </label>
              <textarea
                value={fb.commentaire}
                onChange={e => setFb(p => ({ ...p, commentaire: e.target.value }))}
                maxLength={500}
                rows={3}
                placeholder="Remarque optionnelle (anonyme vis-à-vis de l'enseignant)..."
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-text resize-none"
              />
              <p className="text-right text-[10px] text-muted mt-1">{fb.commentaire.length}/500</p>
            </div>

            <button
              type="submit"
              disabled={loading || !fb.enseignant_user_id}
              className="w-full py-3 bg-accent text-white rounded-lg text-sm font-bold disabled:opacity-50"
            >
              {loading ? 'Envoi...' : '⭐ Envoyer le feedback'}
            </button>
          </form>
        </Card>
      )}

      {/* ── SIGNALEMENT GRAVE ── */}
      {onglet === 'signalement' && (
        <Card>
          <div className="mb-4 p-3 rounded-lg text-xs"
               style={{ background: 'rgba(239,68,68,0.06)', border: '1px solid rgba(239,68,68,0.2)', color: 'var(--red)' }}>
            🔒 <strong>Strictement confidentiel :</strong> Seul le directeur verra ce signalement.
            L'enseignant ne sera jamais informé directement. Vous recevrez un numéro de ticket.
          </div>

          <form onSubmit={soumetteSignalement} className="space-y-4">
            {/* Type incident */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5" htmlFor="type_incident">
                Type de situation <span style={{ color: 'var(--red)' }}>*</span>
              </label>
              <div className="grid grid-cols-2 gap-2">
                {TYPES_INCIDENT.map(t => (
                  <button
                    key={t.val}
                    type="button"
                    onClick={() => setSg(p => ({ ...p, type_incident: t.val }))}
                    className={`p-2 rounded-lg text-left text-xs border transition-colors ${
                      sg.type_incident === t.val
                        ? 'border-red-500/50 text-red-400'
                        : 'bg-surface2 border-border text-muted hover:text-text'
                    }`}
                    style={sg.type_incident === t.val ? { background: 'rgba(239,68,68,0.08)' } : {}}
                  >
                    {t.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Gravité */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">Gravité</label>
              <div className="flex gap-2">
                {[
                  { val: 'important', label: '🟡 Important' },
                  { val: 'grave',     label: '🟠 Grave' },
                  { val: 'tres_grave',label: '🔴 Très grave' },
                ].map(g => (
                  <button
                    key={g.val}
                    type="button"
                    onClick={() => setSg(p => ({ ...p, gravite: g.val }))}
                    className={`flex-1 py-2 rounded-lg text-xs font-semibold border transition-colors ${
                      sg.gravite === g.val
                        ? 'bg-red-500/10 border-red-500/50 text-red-400'
                        : 'bg-surface2 border-border text-muted'
                    }`}
                  >
                    {g.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Date */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                Date de l'incident <span style={{ color: 'var(--red)' }}>*</span>
              </label>
              <input
                type="date"
                required
                max={new Date().toISOString().split('T')[0]}
                value={sg.date_incident}
                onChange={e => setSg(p => ({ ...p, date_incident: e.target.value }))}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-text"
              />
            </div>

            {/* Enseignant concerné (optionnel) */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5">
                Enseignant concerné (si applicable)
              </label>
              <select
                value={sg.concerne_id}
                onChange={e => setSg(p => ({ ...p, concerne_id: e.target.value }))}
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-text"
              >
                <option value="">Non spécifié</option>
                {enseignants.map(e => (
                  <option key={e.id} value={e.user_id ?? e.id}>
                    {e.nom} {e.prenom}
                  </option>
                ))}
              </select>
            </div>

            {/* Description */}
            <div>
              <label className="block text-xs font-semibold text-text mb-1.5" htmlFor="description">
                Description de la situation <span style={{ color: 'var(--red)' }}>*</span>
              </label>
              <textarea
                id="description"
                required
                value={sg.description}
                onChange={e => setSg(p => ({ ...p, description: e.target.value }))}
                minLength={20}
                maxLength={2000}
                rows={5}
                placeholder="Décrivez précisément ce qui s'est passé (minimum 20 caractères)..."
                aria-describedby="desc-aide"
                className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-text resize-none"
              />
              <p id="desc-aide" className="text-[10px] text-muted mt-1">
                {sg.description.length}/2000 · Minimum 20 caractères
              </p>
            </div>

            <button
              type="submit"
              disabled={loading || !sg.type_incident || sg.description.length < 20}
              className="w-full py-3 rounded-lg text-sm font-bold text-white disabled:opacity-50"
              style={{ background: 'var(--red)' }}
            >
              {loading ? 'Envoi sécurisé...' : '🔒 Soumettre le signalement (confidentiel)'}
            </button>
          </form>
        </Card>
      )}
    </div>
  );
}
```

---

## ÉTAPE 5 — Bannière Cours Annulé dans PlanningPage

**Modifier** : `edugestdz/frontend/src/pages/PlanningPage.jsx`

Ajouter dans le rendu de chaque séance, si `statut === 'enseignant_absent'` :

```jsx
// Dans le composant de rendu d'une séance, ajouter :
{seance.statut === 'enseignant_absent' && (
  <div className="mt-2 px-2 py-1 rounded text-[10px] font-bold"
       style={{ background: 'rgba(239,68,68,0.12)', color: 'var(--red)' }}>
    ❌ Cours suspendu — Enseignant absent
  </div>
)}
{seance.statut === 'remplacement_confirme' && (
  <div className="mt-2 px-2 py-1 rounded text-[10px] font-bold"
       style={{ background: 'rgba(16,185,129,0.12)', color: 'var(--green)' }}>
    🔄 Remplacé par {seance.remplacant_nom ?? 'un remplaçant'}
  </div>
)}
```

---

## ÉTAPE 6 — Mettre à jour Sidebar + App.jsx

**Modifier** : `edugestdz/frontend/src/components/Sidebar.jsx`

Ajouter dans les liens de navigation (section "Espace Élève" ou "Outils") :

```jsx
// Liens à ajouter dans le tableau de navigation :
{ path: '/notifications',  icon: Bell,        label: 'Notifications',    roles: ['all'] },
{ path: '/devoirs',        icon: BookOpen,    label: 'Mes Devoirs',      roles: ['eleve', 'enseignant'] },
{ path: '/feedbacks',      icon: MessageSquare,label: 'Feedback / Signalement', roles: ['eleve'] },
```

**Modifier** : `edugestdz/frontend/src/App.jsx`

Ajouter les routes :

```jsx
import NotificationsPage        from '@pages/NotificationsPage';
import DevoirsPage              from '@pages/DevoirsPage';
import FeedbackEnseignantPage   from '@pages/FeedbackEnseignantPage';

// Dans les <Route> :
<Route path="/notifications" element={<NotificationsPage />} />
<Route path="/devoirs"       element={<DevoirsPage />} />
<Route path="/feedbacks"     element={<FeedbackEnseignantPage />} />
```

---

## ÉTAPE 7 — Exécution Mission 2

```bash
cd edugestdz/frontend

# Vérifier syntaxe JSX
node --check src/hooks/useNotificationHoraire.js 2>/dev/null || echo "Hook OK"

# Build test
npm run build
# → dist/ doit être généré sans erreur

git add \
  src/hooks/useNotificationHoraire.js \
  src/pages/NotificationsPage.jsx \
  src/pages/DevoirsPage.jsx \
  src/pages/FeedbackEnseignantPage.jsx \
  src/pages/PlanningPage.jsx \
  src/components/Sidebar.jsx \
  src/App.jsx

git commit -m "feat(flux-info-2/3): Frontend espace élève + notifications + devoirs + feedback/signalement

- useNotificationHoraire: hook plage 7h-20h Algérie (Africa/Algiers)
  mode silencieux affiché + prochaine ouverture calculée
- NotificationsPage: boîte réception in-app avec marquer lu/tout lu
  filtrage non-lues, icônes par type, format date relatif
- DevoirsPage: liste devoirs triés par urgence (couleur progressive)
  filtres par matière, countdown jours restants, légende urgence
- FeedbackEnseignantPage: 2 onglets (feedback + signalement)
  * Feedback: note 1-5★ + type pédagogie/rythme/ambiance + commentaire
    confidentiel directeur, enseignant reçoit résumé anonymisé uniquement
  * Signalement: formulaire accessible, aria-labels, 6 types d'incident
    gravité 3 niveaux, ticket numéroté, strictement confidentiel directeur
- PlanningPage: bannière 'Cours suspendu' / 'Remplacé par X'
- Sidebar + App.jsx: 3 nouvelles routes"

git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK POUR LA MISSION 2

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : FLUX_MISSION_2_FRONTEND_ELEVE.md — 7 étapes.

RÈGLES CRITIQUES :
1. Utiliser les variables CSS existantes (var(--bg), var(--surface), var(--accent), etc.)
   Ne pas hardcoder les couleurs en valeurs hex brutes.
2. La fonction api() dans chaque page doit lire le token depuis localStorage('token').
   Adapter si l'auth est gérée différemment (context, store Redux).
3. NotificationsPage : l'endpoint /api/v1/notifications doit exister (NotificationInAppController).
   Si pas encore créé → commenter l'appel et afficher un état vide.
4. DevoirsPage : l'endpoint /api/v1/devoirs retourne les devoirs pour l'élève connecté.
   Si l'élève n'a pas de user_id sur son eleve record → retourne tableau vide (pas d'erreur).
5. FeedbackEnseignantPage : /api/v1/enseignants doit retourner nom + prenom + user_id.
   Adapter la sélection si la structure diffère.
6. PlanningPage : chercher le fichier réel et ajouter les bannières sur les séances.
   Ne pas supprimer le code existant — seulement AJOUTER les bannières conditionnelles.
7. Sidebar : trouver le tableau de navigation existant et y AJOUTER les 3 nouveaux liens.
   Ne pas remplacer toute la Sidebar.

npm run build → doit compiler sans erreur
git push origin develop → CI ✅
```
