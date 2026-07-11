import React from 'react';

const STATUT_CONFIG = {
  planifiée:  { bg: '#EEF4FF', border: '#1E5EBC', text: '#1E5EBC' },
  en_cours:   { bg: '#FFF8EC', border: '#F39C12', text: '#E08E0B' },
  terminée:   { bg: '#EDFAF3', border: '#27AE60', text: '#229A54' },
  annulée:    { bg: '#FDECEA', border: '#E74C3C', text: '#C0392B' },
  reportée:   { bg: '#F5F5F5', border: '#95A5A6', text: '#7F8C8D' },
};

const BANNIERE_CONFIG = {
  en_cours:   { label: 'En cours',  bg: '#F39C12', textColor: '#fff' },
  terminee:   { label: 'Terminée',  bg: '#27AE60', textColor: '#fff' },
  a_venir:    { label: 'À venir',   bg: '#2563EB', textColor: '#fff' },
  aujourd_hui:{ label: "Aujourd'hui", bg: '#7C3AED', textColor: '#fff' },
};

function getSeanceBanner(seance) {
  const now = new Date();
  const currentHour = now.getHours();
  const currentMin  = now.getMinutes();
  const currentTime = currentHour * 60 + currentMin;

  const [sh, sm] = (seance.heure_debut || '0:0').split(':').map(Number);
  const [eh, em] = (seance.heure_fin || '0:0').split(':').map(Number);
  const startTime = sh * 60 + sm;
  const endTime   = eh * 60 + em;

  if (seance.statut === 'annulée') return null;
  if (seance.statut === 'reportée') return null;

  if (currentTime >= startTime && currentTime < endTime) {
    return BANNIERE_CONFIG.en_cours;
  }
  if (currentTime >= endTime || seance.statut === 'terminée') {
    return BANNIERE_CONFIG.terminee;
  }
  return BANNIERE_CONFIG.a_venir;
}

function isToday(seance) {
  const joursMap = { 0: 6, 1: 0, 2: 1, 3: 2, 4: 3, 5: 4, 6: 5 };
  return seance.jour_num === joursMap[new Date().getDay()];
}

export default function SeanceCard({ seance, onClick }) {
  const config  = STATUT_CONFIG[seance.statut] || STATUT_CONFIG.planifiée;
  const couleur = seance.couleur || config.border;
  const banner  = getSeanceBanner(seance);
  const today   = isToday(seance);

  return (
    <div
      onClick={onClick}
      className="rounded-lg p-1.5 mb-1 cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 border-l-3 text-xs select-none"
      style={{
        backgroundColor: couleur + '18',
        borderLeftColor: couleur,
        borderLeftWidth: '3px',
      }}
    >
      {today && banner && (
        <div
          className="rounded px-1.5 py-0.5 mb-1 text-[9px] font-extrabold uppercase tracking-wider text-center"
          style={{ background: banner.bg, color: banner.textColor }}
        >
          {banner.label}
        </div>
      )}
      <div className="font-bold truncate" style={{ color: couleur }}>
        {seance.matiere || 'Cours'}
      </div>
      <div className="text-neutral-600 truncate mt-0.5">{seance.groupe}</div>
      <div className="flex items-center justify-between mt-1 text-neutral-400">
        <span className="font-mono text-[10px]">
          {seance.heure_debut?.substring(0,5)} - {seance.heure_fin?.substring(0,5)}
        </span>
        {seance.salle && (
          <span className="text-[10px] bg-white/50 px-1 rounded">{seance.salle}</span>
        )}
      </div>
    </div>
  );
}
