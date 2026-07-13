import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';

const WILAYAS_POSITIONS = [
  { id: 1, code: '01', nom: 'Adrar', x: 150, y: 80 },
  { id: 2, code: '02', nom: 'Chlef', x: 280, y: 180 },
  { id: 3, code: '03', nom: 'Laghouat', x: 300, y: 280 },
  { id: 4, code: '04', nom: 'Oum El Bouaghi', x: 480, y: 220 },
  { id: 5, code: '05', nom: 'Batna', x: 520, y: 180 },
  { id: 6, code: '06', nom: 'Béjaïa', x: 480, y: 140 },
  { id: 7, code: '07', nom: 'Biskra', x: 480, y: 320 },
  { id: 8, code: '08', nom: 'Béchar', x: 150, y: 250 },
  { id: 9, code: '09', nom: 'Blida', x: 310, y: 200 },
  { id: 10, code: '10', nom: 'Bouira', x: 380, y: 180 },
  { id: 11, code: '11', nom: 'Tamanrasset', x: 300, y: 420 },
  { id: 12, code: '12', nom: 'Tébessa', x: 560, y: 180 },
  { id: 13, code: '13', nom: 'Tlemcen', x: 200, y: 140 },
  { id: 14, code: '14', nom: 'Tiaret', x: 280, y: 220 },
  { id: 15, code: '15', nom: 'Tizi Ouzou', x: 400, y: 140 },
  { id: 16, code: '16', nom: 'Alger', x: 340, y: 190 },
  { id: 17, code: '17', nom: 'Djelfa', x: 340, y: 300 },
  { id: 18, code: '18', nom: 'Jijel', x: 440, y: 140 },
  { id: 19, code: '19', nom: 'Sétif', x: 440, y: 180 },
  { id: 20, code: '20', nom: 'Saïda', x: 260, y: 260 },
  { id: 21, code: '21', nom: 'Skikda', x: 460, y: 140 },
  { id: 22, code: '22', nom: 'Sidi Bel Abbès', x: 220, y: 180 },
  { id: 23, code: '23', nom: 'Annaba', x: 540, y: 140 },
  { id: 24, code: '24', nom: 'Guelma', x: 520, y: 180 },
  { id: 25, code: '25', nom: 'Constantine', x: 500, y: 200 },
  { id: 26, code: '26', nom: 'Médéa', x: 320, y: 220 },
  { id: 27, code: '27', nom: 'Mostaganem', x: 260, y: 180 },
  { id: 28, code: '28', nom: "M'sila", x: 400, y: 240 },
  { id: 29, code: '29', nom: 'Mascara', x: 240, y: 240 },
  { id: 30, code: '30', nom: 'Ouargla', x: 440, y: 340 },
  { id: 31, code: '31', nom: 'Oran', x: 230, y: 170 },
  { id: 32, code: '32', nom: 'El Bayadh', x: 220, y: 260 },
  { id: 33, code: '33', nom: 'Illizi', x: 540, y: 400 },
  { id: 34, code: '34', nom: 'Bordj Bou Arréridj', x: 460, y: 200 },
  { id: 35, code: '35', nom: 'Boumerdès', x: 380, y: 190 },
  { id: 36, code: '36', nom: 'El Tarf', x: 560, y: 150 },
  { id: 37, code: '37', nom: 'Tindouf', x: 80, y: 280 },
  { id: 38, code: '38', nom: 'Tissemsilt', x: 340, y: 240 },
  { id: 39, code: '39', nom: 'El Oued', x: 500, y: 320 },
  { id: 40, code: '40', nom: 'Khenchela', x: 540, y: 220 },
  { id: 41, code: '41', nom: 'Souk Ahras', x: 570, y: 180 },
  { id: 42, code: '42', nom: 'Tipaza', x: 300, y: 190 },
  { id: 43, code: '43', nom: 'Mila', x: 500, y: 180 },
  { id: 44, code: '44', nom: 'Aïn Defla', x: 320, y: 210 },
  { id: 45, code: '45', nom: 'Naâma', x: 220, y: 320 },
  { id: 46, code: '46', nom: 'Aïn Témouchent', x: 220, y: 200 },
  { id: 47, code: '47', nom: 'Ghardaïa', x: 350, y: 360 },
  { id: 48, code: '48', nom: 'Relizane', x: 280, y: 200 },
  { id: 49, code: '49', nom: 'El M\'Ghair', x: 460, y: 360 },
  { id: 50, code: '50', nom: 'El Meniaa', x: 320, y: 380 },
  { id: 51, code: '51', nom: 'Ouled Djellal', x: 440, y: 300 },
  { id: 52, code: '52', nom: 'Bordj Badji Mokhtar', x: 180, y: 420 },
  { id: 53, code: '53', nom: 'Béni Abbès', x: 130, y: 340 },
  { id: 54, code: '54', nom: 'Timimoun', x: 180, y: 140 },
  { id: 55, code: '55', nom: 'Touggourt', x: 460, y: 380 },
  { id: 56, code: '56', nom: 'Djanet', x: 500, y: 440 },
  { id: 57, code: '57', nom: 'In Salah', x: 280, y: 420 },
  { id: 58, code: '58', nom: 'In Guezzam', x: 300, y: 480 },
];

function getHeatColor(nbAbsences, max) {
  if (nbAbsences === 0) return '#e5e7eb';
  const ratio = nbAbsences / max;
  if (ratio < 0.2) return '#fef3c7';
  if (ratio < 0.4) return '#fde68a';
  if (ratio < 0.6) return '#fbbf24';
  if (ratio < 0.8) return '#f59e0b';
  return '#dc2626';
}

export default function CarteAbsencesPage() {
  const [wilayaData, setWilayaData] = useState([]);
  const [resume, setResume] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedWilaya, setSelectedWilaya] = useState(null);
  const [filters, setFilters] = useState({ date_debut: '', date_fin: '' });

  useEffect(() => {
    loadData();
  }, [filters]);

  const loadData = async () => {
    setLoading(true);
    try {
      const params = {};
      if (filters.date_debut) params.date_debut = filters.date_debut;
      if (filters.date_fin) params.date_fin = filters.date_fin;

      const [wilayaRes, resumeRes] = await Promise.all([
        api.get('/absences/geographie/par-wilaya', { params }),
        api.get('/absences/geographie/resume', { params }),
      ]);
      setWilayaData(wilayaRes.data?.data || []);
      setResume(resumeRes.data?.data || null);
    } catch {
      setWilayaData([]);
      setResume(null);
    } finally {
      setLoading(false);
    }
  };

  const maxAbsences = Math.max(...wilayaData.map(w => w.nb_absences), 1);

  const getNbAbsences = (wilayaId) => {
    const found = wilayaData.find(w => w.wilaya_id === wilayaId);
    return found ? parseInt(found.nb_absences) : 0;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500" />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Carte Chaleur des Absences</h1>
        <p className="text-gray-500 mt-1">Répartition géographique des absences par wilaya</p>
      </div>

      {/* Filtres */}
      <div className="flex gap-4 items-center">
        <div>
          <label className="block text-xs text-gray-500 mb-1">Date début</label>
          <input
            type="date"
            value={filters.date_debut}
            onChange={e => setFilters(f => ({ ...f, date_debut: e.target.value }))}
            className="border rounded-lg px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-xs text-gray-500 mb-1">Date fin</label>
          <input
            type="date"
            value={filters.date_fin}
            onChange={e => setFilters(f => ({ ...f, date_fin: e.target.value }))}
            className="border rounded-lg px-3 py-2 text-sm"
          />
        </div>
      </div>

      {/* KPIs */}
      {resume && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { label: 'Total absences', value: resume.total_absences, icon: '📊' },
            { label: 'Élèves avec wilaya', value: resume.eleves_avec_wilaya, icon: '📍' },
            { label: 'Wilayas concernées', value: resume.wilayas_concernees, icon: '🗺️' },
            { label: 'Wilayas sans données', value: 58 - resume.wilayas_concernees, icon: '⬜' },
          ].map((kpi, i) => (
            <div key={i} className="bg-white rounded-xl shadow-sm border p-4">
              <div className="text-2xl mb-1">{kpi.icon}</div>
              <div className="text-xl font-bold text-gray-900">{kpi.value}</div>
              <div className="text-xs text-gray-500">{kpi.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Carte SVG */}
      <div className="bg-white rounded-xl shadow-sm border p-6">
        <h3 className="font-semibold text-gray-900 mb-4">Carte de l'Algérie</h3>
        <svg viewBox="0 0 700 560" className="w-full max-w-4xl mx-auto">
          {WILAYAS_POSITIONS.map(w => {
            const nb = getNbAbsences(w.id);
            const color = getHeatColor(nb, maxAbsences);
            const isSelected = selectedWilaya?.id === w.id;
            return (
              <g key={w.id} onClick={() => setSelectedWilaya(isSelected ? null : w)} className="cursor-pointer">
                <circle
                  cx={w.x}
                  cy={w.y}
                  r={isSelected ? 18 : 14}
                  fill={color}
                  stroke={isSelected ? '#1d4ed8' : '#9ca3af'}
                  strokeWidth={isSelected ? 3 : 1}
                  className="transition-all duration-200"
                />
                <text
                  x={w.x}
                  y={w.y + 1}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  className="text-[8px] font-bold pointer-events-none"
                  fill={nb > maxAbsences * 0.6 ? '#fff' : '#374151'}
                >
                  {w.code}
                </text>
              </g>
            );
          })}
        </svg>

        {/* Légende */}
        <div className="flex items-center justify-center gap-2 mt-4 text-xs text-gray-500">
          <span>0</span>
          {['#e5e7eb', '#fef3c7', '#fde68a', '#fbbf24', '#f59e0b', '#dc2626'].map((c, i) => (
            <div key={i} className="w-6 h-4 rounded" style={{ backgroundColor: c }} />
          ))}
          <span>{maxAbsences}+</span>
        </div>
      </div>

      {/* Détail wilaya sélectionnée */}
      {selectedWilaya && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <h3 className="font-semibold text-gray-900 mb-2">
            {selectedWilaya.nom} — {getNbAbsences(selectedWilaya.id)} absence(s)
          </h3>
          <div className="text-sm text-gray-500">
            Code wilaya : {selectedWilaya.code}
          </div>
        </div>
      )}

      {/* Top 5 wilayas */}
      {resume?.top_5_wilayas?.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div className="px-6 py-4 border-b">
            <h3 className="font-semibold text-gray-900">Top 5 Wilayas</h3>
          </div>
          <div className="divide-y">
            {resume.top_5_wilayas.map((w, i) => (
              <div key={i} className="px-6 py-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <span className="text-lg font-bold text-gray-400">#{i + 1}</span>
                  <span className="font-medium text-gray-900">{w.nom_fr || 'Sans wilaya'}</span>
                </div>
                <span className="text-sm font-bold text-red-600">{w.nb_absences} abs.</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
