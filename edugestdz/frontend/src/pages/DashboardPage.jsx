import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '@api/axiosInstance';
import StatCard from '@components/dashboard/StatCard';

export default function DashboardPage() {
  const [data, setData] = useState(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await api.get('/dashboard');
        setData(res);
      } catch { /* silent */ }
    };
    load();
  }, []);

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
