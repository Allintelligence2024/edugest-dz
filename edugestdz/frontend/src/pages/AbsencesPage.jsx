import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const DEMO_DATA = {
  date: new Date().toLocaleDateString('fr-DZ', { weekday: 'long', day: 'numeric', month: 'long' }),
  stats: { total: 24, absents: 5, presents: 16, retards: 3 },
  data: [
    { id: '1', statut: 'absent', eleve: { nom: 'BENALI', prenom: 'Amine', niveau_scolaire: '3AS' }, sms_parent_envoye: true },
    { id: '2', statut: 'absent', eleve: { nom: 'MEKKI', prenom: 'Sara', niveau_scolaire: '2AS' }, sms_parent_envoye: false },
    { id: '3', statut: 'retard', eleve: { nom: 'REZGUI', prenom: 'Karim', niveau_scolaire: '1AS' }, heure_arrivee: '08:47', sms_parent_envoye: false },
    { id: '4', statut: 'absent', eleve: { nom: 'BOUZID', prenom: 'Lina', niveau_scolaire: '4AM' }, sms_parent_envoye: true },
  ],
};

export default function AbsencesPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const today = new Date().toISOString().split('T')[0];

  useEffect(() => {
    api.get('/absences/jour', { params: { date: today } })
      .then(res => setData(res.data ?? DEMO_DATA))
      .catch(() => setData(DEMO_DATA))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"/></div>;

  const absences = data?.data || [];
  const stats = data?.meta?.stats || data?.stats || {};
  const absentsEtRetards = absences.filter(a => ['absent', 'retard'].includes(a.statut));

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">✅ Absences Journalières</h1>
        <p className="text-gray-500 mt-1">{data?.date || today}</p>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Élèves absents', value: stats.absents ?? 0, color: 'red', icon: '❌' },
          { label: 'En retard', value: stats.retards ?? 0, color: 'orange', icon: '⏰' },
          { label: 'Présents', value: stats.presents ?? 0, color: 'green', icon: '✅' },
          { label: 'SMS envoyés', value: absences.filter(a => a.sms_parent_envoye).length, color: 'blue', icon: '📱' },
        ].map((k, i) => (
          <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
            <div className="text-2xl mb-1">{k.icon}</div>
            <div className={`text-xl font-bold text-${k.color}-600`}>{k.value}</div>
            <div className="text-xs text-gray-500">{k.label}</div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-6 py-4 border-b">
          <h3 className="font-semibold text-gray-900">Absents &amp; Retards du jour ({absentsEtRetards.length})</h3>
        </div>
        {absentsEtRetards.length === 0 ? (
          <div className="p-12 text-center">
            <div className="text-4xl mb-3">🎉</div>
            <p className="text-gray-500 font-medium">Tous les élèves sont présents !</p>
          </div>
        ) : (
          <div className="divide-y">
            {absentsEtRetards.map((a, i) => (
              <div key={i} className="px-6 py-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-600">
                    {a.eleve?.prenom?.[0]}{a.eleve?.nom?.[0]}
                  </div>
                  <div>
                    <p className="font-medium text-gray-900">{a.eleve?.prenom} {a.eleve?.nom}</p>
                    <p className="text-xs text-gray-500">{a.eleve?.niveau_scolaire}
                      {a.statut === 'retard' && a.heure_arrivee && ` · Arrivé à ${a.heure_arrivee.slice(0, 5)}`}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  {a.sms_parent_envoye ? (
                    <span className="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">📱 SMS envoyé</span>
                  ) : (
                    <span className="text-xs text-gray-400">SMS non envoyé</span>
                  )}
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${a.statut === 'absent' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'}`}>
                    {a.statut === 'absent' ? '❌ Absent' : '⏰ Retard'}
                  </span>
                  <button className="text-blue-600 hover:text-blue-800 text-xs font-medium">Justifier</button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
