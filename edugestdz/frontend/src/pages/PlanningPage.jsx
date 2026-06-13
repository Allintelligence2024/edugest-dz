import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';
import SeanceCard from '@components/planning/SeanceCard';
import CoursModal from '@components/planning/CoursModal';
import PlanningFilters from '@components/planning/PlanningFilters';

const JOURS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const HEURES = Array.from({ length: 14 }, (_, i) => {
  const h = String(i + 8).padStart(2, '0');
  return `${h}:00`;
});

export default function PlanningPage() {
  const [semaine, setSemaine] = useState([]);
  const [filtres, setFiltres] = useState({});
  const [coursModalOpen, setCoursModalOpen] = useState(false);
  const [editingCours, setEditingCours] = useState(null);
  const [dateRange, setDateRange] = useState(() => getWeekRange(new Date()));

  function getWeekRange(d) {
    const start = new Date(d);
    const day = start.getDay();
    const diff = start.getDate() - day + (day === 0 ? -6 : 1);
    start.setDate(diff);
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    return { start, end, label: `${start.toLocaleDateString('fr-DZ')} — ${end.toLocaleDateString('fr-DZ')}` };
  }

  const loadSemaine = async () => {
    try {
      const params = {
        date_debut: dateRange.start.toISOString().split('T')[0],
        date_fin: dateRange.end.toISOString().split('T')[0],
        ...filtres,
      };
      const res = await api.get('/planning', { params });
      setSemaine(res.seances || []);
    } catch { /* silent */ }
  };

  useEffect(() => { loadSemaine(); }, [dateRange, filtres]);

  const getSeancesForJourHeure = (jourNum, heure) => {
    const [h] = heure.split(':').map(Number);
    return semaine.filter(s => {
      const [dh] = s.heure_debut.split(':').map(Number);
      const [fh] = s.heure_fin.split(':').map(Number);
      return s.jour_num === jourNum && dh <= h && fh > h;
    });
  };

  const handlePrevWeek = () => {
    const d = new Date(dateRange.start);
    d.setDate(d.getDate() - 7);
    setDateRange(getWeekRange(d));
  };
  const handleNextWeek = () => {
    const d = new Date(dateRange.start);
    d.setDate(d.getDate() + 7);
    setDateRange(getWeekRange(d));
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">📅 Planning hebdomadaire</h1>
          <p className="text-neutral-500 text-sm mt-1">{dateRange.label}</p>
        </div>
        <button onClick={() => { setEditingCours(null); setCoursModalOpen(true); }} className="px-5 py-2.5 bg-primary-600 text-white rounded-xl font-semibold text-sm hover:bg-primary-700 transition-colors">
          ➕ Nouveau cours
        </button>
      </div>

      <PlanningFilters filtres={filtres} onChange={setFiltres} />

      <div className="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div className="flex items-center justify-between p-3 border-b border-neutral-100">
          <button onClick={handlePrevWeek} className="px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50 rounded-lg">← Semaine précédente</button>
          <button onClick={() => window.location.reload()} className="px-4 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 rounded-lg">Aujourd'hui</button>
          <button onClick={handleNextWeek} className="px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50 rounded-lg">Semaine suivante →</button>
        </div>
        <div className="overflow-x-auto">
          <div className="grid grid-cols-8 min-w-[800px]">
            <div className="border-r border-neutral-100 bg-neutral-50 p-2">
              <div className="text-xs font-bold text-neutral-400 uppercase text-center">Horaire</div>
            </div>
            {JOURS.map((jour, idx) => (
              <div key={idx} className={`border-r border-neutral-100 p-2 text-center ${new Date().getDay() === idx ? 'bg-primary-50' : 'bg-neutral-50'}`}>
                <div className="text-xs font-bold text-neutral-600 uppercase">{jour.substring(0, 3)}</div>
                <div className="text-2xl font-bold text-neutral-800">{new Date(dateRange.start).getDate() + idx}</div>
              </div>
            ))}
            {HEURES.map(heure => (
              <React.Fragment key={heure}>
                <div className="border-r border-b border-neutral-100 p-1.5 text-center bg-neutral-50">
                  <span className="text-[10px] font-mono text-neutral-400">{heure}</span>
                </div>
                {[0,1,2,3,4,5,6].map(jourNum => {
                  const seances = getSeancesForJourHeure(jourNum, heure);
                  const isToday = new Date().getDay() === jourNum;
                  return (
                    <div key={`${jourNum}-${heure}`} className={`border-r border-b border-neutral-100 p-0.5 min-h-[60px] ${isToday ? 'bg-primary-50/30' : ''}`}>
                      {seances.map(s => (
                        <SeanceCard key={s.id} seance={s} onClick={() => { setEditingCours(s); setCoursModalOpen(true); }} />
                      ))}
                    </div>
                  );
                })}
              </React.Fragment>
            ))}
          </div>
        </div>
      </div>

      <CoursModal isOpen={coursModalOpen} onClose={() => { setCoursModalOpen(false); setEditingCours(null); }} cours={editingCours} onSuccess={() => { setCoursModalOpen(false); setEditingCours(null); loadSemaine(); }} />
    </div>
  );
}
