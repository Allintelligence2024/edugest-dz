import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@hooks/useAuth';
import api from '@api/axiosInstance';
import StatCard from '@components/dashboard/StatCard';

// ── Données de démo pour le tableau de bord ────────────────
const DEMO_DATA = {
  stats: {
    eleves_actifs: 147,
    nouveaux_eleves_mois: 12,
    enseignants_actifs: 18,
    groupes_actifs: 24,
    seances_semaine: 56,
    seances_ajd: 8,
    revenu_mensuel: 485000,
    impayes: 62000,
    factures_impayees: 7,
  },
  seances_aujourdhui: [
    { id: 1, matiere: 'Mathématiques', groupe: '1AS G1', enseignant: 'M. Benali', heure_debut: '08:30', heure_fin: '10:00' },
    { id: 2, matiere: 'Physique', groupe: '2AS G2', enseignant: 'Mme Hadj', heure_debut: '10:15', heure_fin: '11:45' },
    { id: 3, matiere: 'Sciences naturelles', groupe: '3AS G1', enseignant: 'M. Amrani', heure_debut: '13:00', heure_fin: '14:30' },
    { id: 4, matiere: 'Français', groupe: '4AM G3', enseignant: 'Mme Slimane', heure_debut: '14:45', heure_fin: '16:15' },
    { id: 5, matiere: 'Anglais', groupe: '1AS G2', enseignant: 'M. Khedim', heure_debut: '16:30', heure_fin: '18:00' },
  ],
  paiements_recents: [
    { id: 1, eleve: 'Amine Boudiaf', montant: 4500, mode_paiement: 'Espèces', date: '2026-06-15' },
    { id: 2, eleve: 'Sara Mekki', montant: 3000, mode_paiement: 'Virement CCP', date: '2026-06-14' },
    { id: 3, eleve: 'Karim Rezgui', montant: 6000, mode_paiement: 'Espèces', date: '2026-06-14' },
    { id: 4, eleve: 'Lina Bouzid', montant: 4500, mode_paiement: 'BaridiMob', date: '2026-06-13' },
  ],
};

export default function DashboardPage() {
  const [data, setData] = useState(null);
  const { isDemoMode } = useAuth();

  useEffect(() => {
    const load = async () => {
      if (isDemoMode) {
        // Simuler un petit délai de chargement pour le réalisme
        setTimeout(() => setData(DEMO_DATA), 400);
        return;
      }
      try {
        const res = await api.get('/dashboard');
        setData(res);
      } catch {
        // Fallback : données de démo si API inaccessible
        setData(DEMO_DATA);
      }
    };
    load();
  }, [isDemoMode]);

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-3 border-primary-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  const { stats, seances_aujourdhui, paiements_recents } = data;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-neutral-800">Tableau de bord</h1>
        <p className="text-neutral-500 mt-1">Vue d'ensemble de votre centre</p>
        {isDemoMode && (
          <div className="mt-2 inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50 border border-amber-200 rounded-xl text-xs font-medium text-amber-700">
            ⚡ Mode démo — données fictives
          </div>
        )}
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Link to="/eleves">
          <StatCard icon="👨‍🎓" label="Élèves actifs" value={stats?.eleves_actifs ?? 0} sub={stats?.nouveaux_eleves_mois ? `+${stats.nouveaux_eleves_mois} ce mois` : undefined} color="blue" />
        </Link>
        <Link to="/enseignants">
          <StatCard icon="👨‍🏫" label="Enseignants" value={stats?.enseignants_actifs ?? 0} color="purple" />
        </Link>
        <Link to="/groupes">
          <StatCard icon="📚" label="Groupes" value={stats?.groupes_actifs ?? 0} color="green" />
        </Link>
        <Link to="/planning">
          <StatCard icon="📅" label="Séances cette semaine" value={stats?.seances_semaine ?? 0} sub={stats?.seances_ajd ? `${stats.seances_ajd} aujourd'hui` : undefined} color="orange" />
        </Link>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <Link to="/factures">
          <StatCard icon="💰" label="Revenu mensuel" value={`${Number(stats?.revenu_mensuel ?? 0).toLocaleString()} DA`} color="green" />
        </Link>
        <Link to="/factures">
          <StatCard icon="⚠️" label="Impayés" value={`${Number(stats?.impayes ?? 0).toLocaleString()} DA`} sub={`${stats?.factures_impayees ?? 0} factures`} color="red" />
        </Link>
      </div>
      {seances_aujourdhui?.length > 0 && (
        <section>
          <h2 className="text-lg font-bold text-neutral-800 mb-3">📅 Séances aujourd'hui</h2>
          <div className="bg-white rounded-2xl border border-neutral-200 divide-y divide-neutral-100">
            {seances_aujourdhui.map(s => (
              <div key={s.id} className="flex items-center justify-between p-4">
                <div>
                  <p className="font-semibold text-neutral-800">{s.matiere}</p>
                  <p className="text-sm text-neutral-500">{s.groupe} — {s.enseignant}</p>
                </div>
                <span className="font-mono text-sm text-neutral-600">{s.heure_debut?.substring(0,5)} - {s.heure_fin?.substring(0,5)}</span>
              </div>
            ))}
          </div>
        </section>
      )}
      {paiements_recents?.length > 0 && (
        <section>
          <h2 className="text-lg font-bold text-neutral-800 mb-3">💳 Paiements récents</h2>
          <div className="bg-white rounded-2xl border border-neutral-200 divide-y divide-neutral-100">
            {paiements_recents.map(p => (
              <div key={p.id} className="flex items-center justify-between p-4">
                <div>
                  <p className="font-semibold text-neutral-800">{p.eleve}</p>
                  <p className="text-xs text-neutral-400">{p.mode_paiement}</p>
                </div>
                <span className="font-bold text-green-700">{Number(p.montant).toLocaleString()} DA</span>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
