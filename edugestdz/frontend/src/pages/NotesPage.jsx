import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';

const MATIERE_COLORS = { mathématiques: 'bg-blue-100 border-blue-300 text-blue-700', physique: 'bg-purple-100 border-purple-300 text-purple-700', français: 'bg-green-100 border-green-300 text-green-700', anglais: 'bg-cyan-100 border-cyan-300 text-cyan-700', arabe: 'bg-amber-100 border-amber-300 text-amber-700', svt: 'bg-emerald-100 border-emerald-300 text-emerald-700', histoire: 'bg-orange-100 border-orange-300 text-orange-700', philosophie: 'bg-rose-100 border-rose-300 text-rose-700', informatique: 'bg-indigo-100 border-indigo-300 text-indigo-700', default: 'bg-neutral-100 border-neutral-300 text-neutral-700' };

export default function NotesPage() {
  const [search, setSearch] = useState('');
  const [selectedMatiere, setSelectedMatiere] = useState('');
  const [eleveDetail, setEleveDetail] = useState(null);
  const [showDetail, setShowDetail] = useState(null);
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [refreshKey, setRefreshKey] = useState(0);
  const [matieres, setMatieres] = useState([]);

  const query = new URLSearchParams({ search, page, per_page: perPage, matiere: selectedMatiere }).toString();
  const { data, isLoading } = useList('/notes', query, 'notes', refreshKey);

  const loadMatieres = useCallback(async () => {
    try { const res = await api.get('/matieres'); setMatieres(res.data || []); }
    catch { /* ignore */ }
  }, []);

  React.useEffect(() => { loadMatieres(); }, [loadMatieres]);

  const handleAddNote = async (noteData) => {
    try { await api.post('/notes', noteData); toast.success('Note enregistrée 🎉'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  };

  const handleDeleteNote = async (id) => {
    if (!window.confirm('Supprimer cette note ?')) return;
    try { await api.delete(`/notes/${id}`); toast.success('Note supprimée'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  };

  const COLUMNS = [
    { key: 'eleve.nom', label: 'Élève', render: (v, r) => (
      <button onClick={() => { setShowDetail(r.eleve_id); setEleveDetail(r.eleve); }}
              className="font-semibold text-neutral-800 hover:text-primary-600 transition-colors">{r.eleve?.nom} {r.eleve?.prenom}</button>
    )},
    { key: 'eleve.niveau_scolaire', label: 'Niveau', render: v => <span className="badge badge-neutral">{v}</span> },
    { key: 'note', label: 'Note', render: (v, r) => (
      <span className={`font-bold text-lg ${r.absent ? 'text-red-500' : v >= (r.note_sur * 0.5) ? 'text-green-600' : 'text-red-500'}`}>
        {r.absent ? 'ABS' : `${v}/${r.note_sur}`}
      </span>
    )},
    { key: 'matiere.nom_fr', label: 'Matière', render: (v, r) => {
      const colorClass = MATIERE_COLORS[r.matiere?.nom?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')] || MATIERE_COLORS[r.matiere?.slug] || MATIERE_COLORS.default;
      return <span className={`text-xs px-2 py-1 rounded-full border font-medium ${colorClass}`}>{r.matiere?.nom_fr || v}</span>;
    }},
    { key: 'type', label: 'Type', render: v => <span className="text-xs text-neutral-400 uppercase">{v || 'devoir'}</span> },
    { key: 'date', label: 'Date', render: v => <span className="text-sm text-neutral-500">{new Date(v).toLocaleDateString('fr-DZ')}</span> },
    { key: 'id', label: '', render: (v, r) => (
      <button onClick={() => handleDeleteNote(v)} className="p-1.5 hover:bg-red-50 rounded-lg text-red-400 text-sm">🗑️</button>
    )},
  ];

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">📊 Notes & Évaluations</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Suivez les notes et les moyennes des élèves</p>
        </div>
        <button onClick={() => setShowDetail('new')} className="btn btn-primary gap-2">➕ Nouvelle note</button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1"><SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher un élève..." /></div>
          <select value={selectedMatiere} onChange={e => { setSelectedMatiere(e.target.value); setPage(1); }} className="input max-w-[200px]">
            <option value="">Toutes les matières</option>
            {matieres.map(m => <option key={m.id} value={m.id}>{m.nom_fr}</option>)}
          </select>
        </div>

        <DataTable columns={COLUMNS} data={data?.data || []} isLoading={isLoading} emptyMessage="Aucune note trouvée" />
        <Pagination currentPage={page} lastPage={data?.meta?.last_page || 1} total={data?.meta?.total || 0} perPage={perPage} onPageChange={setPage} />
      </div>

      {showDetail && <NoteModal eleve={eleveDetail} matieres={matieres} onClose={() => setShowDetail(null)} onSuccess={() => { setShowDetail(null); setRefreshKey(k => k + 1); }} />}
    </div>
  );
}

function NoteModal({ eleve, matieres, onClose, onSuccess }) {
  const [form, setForm] = useState({ eleve_id: eleve?.id || '', matiere_id: '', note: '', note_sur: 20, type: 'devoir', date: new Date().toISOString().split('T')[0], absent: false, appreciation: '' });
  const [isLoading, setIsLoading] = useState(false);
  const [eleveSearch, setEleveSearch] = useState('');
  const [eleves, setEleves] = useState([]);

  const loadEleves = async (q) => {
    if (!q || q.length < 2) return;
    try { const r = await api.get('/eleves', { params: { search: q, per_page: 10 } }); setEleves(r.data || []); }
    catch { /* ignore */ }
  };

  const submit = async () => {
    setIsLoading(true);
    try {
      if (form.eleve_id) { await api.post('/notes', form); toast.success('Note enregistrée 🎉'); onSuccess(); }
      else toast.error('Sélectionnez un élève');
    } catch (err) { toast.error(err?.error?.message || 'Erreur'); }
    finally { setIsLoading(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md p-5 animate-slide-up">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">📝 Nouvelle note</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg">✕</button>
        </div>
        <div className="space-y-3">
          <div>
            <label className="label">Élève</label>
            <input value={eleveSearch} onChange={e => { setEleveSearch(e.target.value); loadEleves(e.target.value); }} className="input" placeholder="Rechercher un élève..." />
            {eleves.length > 0 && (
              <div className="mt-1 border border-neutral-200 rounded-xl max-h-32 overflow-y-auto">
                {eleves.filter(e => !form.eleve_id || form.eleve_id !== e.id).map(e => (
                  <button key={e.id} type="button" onClick={() => { setForm(f => ({...f, eleve_id: e.id})); setEleveSearch(`${e.nom} ${e.prenom}`); setEleves([]); }}
                          className="w-full text-left px-3 py-2 text-sm hover:bg-neutral-50 border-b border-neutral-100 last:border-0">{e.nom} {e.prenom} <span className="text-neutral-400">({e.niveau_scolaire})</span></button>
                ))}
              </div>
            )}
            {form.eleve_id && <p className="text-xs text-green-600 mt-1">✓ Élève sélectionné</p>}
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Matière *</label>
              <select value={form.matiere_id} onChange={e => setForm(f => ({...f, matiere_id: e.target.value}))} className="input">
                <option value="">—</option>
                {matieres.map(m => <option key={m.id} value={m.id}>{m.nom_fr}</option>)}
              </select>
            </div>
            <div>
              <label className="label">Type</label>
              <select value={form.type} onChange={e => setForm(f => ({...f, type: e.target.value}))} className="input">
                {['devoir','examen','composition','interrogation','projet'].map(t => <option key={t} value={t}>{t}</option>)}
              </select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Note *</label>
              <input type="number" step="0.25" min="0" max={form.note_sur} value={form.note} onChange={e => setForm(f => ({...f, note: e.target.value}))}
                     className="input" disabled={form.absent} />
            </div>
            <div>
              <label className="label">Sur</label>
              <input type="number" value={form.note_sur} onChange={e => setForm(f => ({...f, note_sur: Number(e.target.value)}))} className="input" />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <input type="checkbox" id="absent" checked={form.absent} onChange={e => setForm(f => ({...f, absent: e.target.checked, note: ''}))} className="rounded" />
            <label htmlFor="absent" className="text-sm text-neutral-600">Absent (aucune note)</label>
          </div>
          <div>
            <label className="label">Date</label>
            <input type="date" value={form.date} onChange={e => setForm(f => ({...f, date: e.target.value}))} className="input" />
          </div>
          <div>
            <label className="label">Appréciation</label>
            <textarea value={form.appreciation} onChange={e => setForm(f => ({...f, appreciation: e.target.value}))} className="input resize-none h-20" placeholder="Commentaire..." />
          </div>
          <button onClick={submit} disabled={isLoading || !form.eleve_id || !form.matiere_id} className="btn btn-primary w-full">
            {isLoading ? '⏳ Enregistrement...' : '✅ Enregistrer la note'}
          </button>
        </div>
      </div>
    </div>
  );
}
