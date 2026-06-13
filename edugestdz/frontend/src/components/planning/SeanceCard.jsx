import React from 'react';

const STATUT_CONFIG = {
  planifiée:  { bg: '#EEF4FF', border: '#1E5EBC', text: '#1E5EBC' },
  en_cours:   { bg: '#FFF8EC', border: '#F39C12', text: '#E08E0B' },
  terminée:   { bg: '#EDFAF3', border: '#27AE60', text: '#229A54' },
  annulée:    { bg: '#FDECEA', border: '#E74C3C', text: '#C0392B' },
  reportée:   { bg: '#F5F5F5', border: '#95A5A6', text: '#7F8C8D' },
};

export default function SeanceCard({ seance, onClick }) {
  const config  = STATUT_CONFIG[seance.statut] || STATUT_CONFIG.planifiée;
  const couleur = seance.couleur || config.border;

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
