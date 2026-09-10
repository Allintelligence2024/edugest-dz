import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import FilterBar from '@components/common/FilterBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';

const FILTERS = [
  { key: 'niveau', label: 'Niveau', options: ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'] },
  { key: 'statut', label: 'Statut', options: ['actif','inactif'] },
];

export default function GroupesPage() {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [sort, setSort] = useState({ key: 'nom', dir: 'asc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [refreshKey, setRefreshKey] = useState(0);
  const [showModal, setShowModal] = useState(false);
  const [editingGroupe, setEditingGroupe] = useState(null);
  const [detailGroupe, setDetailGroupe] = useState(null);
  const [matieres, setMatieres] = useState([]);
  const [enseignants, setEnseignants] = useState([]);

  const query = new URLSearchParams({ search, page, per_page: perPage, sort_by: sort.key, sort_dir: sort.dir, ...filters }).toString();
  const { data, isLoading } = useList('/groupes', query, 'groupes', refreshKey);

  const loadRefs = useCallback(async () => {
    try {
      const [matRes, ensRes] = await Promise.all([api.get('/matieres'), api.get('/enseignants?per_page=100')]);
      setMatieres(matRes.data || []);
      setEnseignants(ensRes.data || []);
    } catch { /* ignore */ }
  }, []);

  React.useEffect(() => { loadRefs(); }, [loadRefs]);

  const handleDelete = useCallback(async (groupe) => {
    if (!window.confirm(`Supprimer le groupe "${groupe.nom}" ?`)) return;
    try { await api.delete(`/groupes/${groupe.id}`); toast.success('Groupe supprimé'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleToggleStatut = useCallback(async (groupe) => {
    try { await api.post(`/groupes/${groupe.id}/toggle-statut`); toast.success(`Statut changé`); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const COLUMNS = [
    { key: 'nom', label: 'Groupe', sortable: true, render: (v, r) => (
      <button onClick={() => setDetailGroupe(r)} className="font-semibold text-left hover:text-primary-600 transition-colors">
        <div>{v || 'Sans nom'}</div>
        <div className="text-xs text-neutral-400 font-normal">{r.matiere?.nom_fr} • {r.niveau}</div>
      </button>
    )},
    { key: 'matiere.nom_fr', label: 'Matière', render: (v, r) => (
      <span className="badge badge-neutral" style={{ borderLeftColor: r.matiere?.couleur || '#ccc', borderLeftWidth: 3 }}>{v}</span>
    )},
    { key: 'niveau', label: 'Niveau', sortable: true, render: v => <span className="badge badge-primary">{v}</span> },
    { key: 'enseignant.nom', label: 'Enseignant', render: (v, r) => {
      const e = r.enseignant;
      return e ? <span className="text-sm">{e.nom} {e.prenom}</span> : <span className="text-sm text-neutral-400 italic">Non assigné</span>;
    }},
    { key: 'capacite_max', label: 'Élèves', render: (v, r) => {
      const ratio = (r.eleves_count || 0) / v;
      const color = ratio >= 1 ? 'text-red-600' : ratio >= 0.75 ? 'text-amber-600' : 'text-green-600';
      return <span className={`font-medium text-sm ${color}`}>{r.eleves_count || 0}/{v}</span>;
    }},
    { key: 'statut', label: 'Statut', sortable: true, render: v => {
      const styles = { actif: 'badge-actif', inactif: 'badge-inactif' };
      return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v}</span>;
    }},
  ];

  const actions = (row) => (
    <div className="flex items-center gap-1">
      <button onClick={() => setDetailGroupe(row)} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm">👁️</button>
      <button onClick={() => { setEditingGroupe(row); setShowModal(true); }} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm">✏️</button>
      <button onClick={() => handleToggleStatut(row)} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm">🔄</button>
      <button onClick={() => handleDelete(row)} className="p-1.5 hover:bg-red-50 rounded-lg text-sm text-red-500">🗑️</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">👥 Groupes</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Organisez les groupes de cours par matière et niveau</p>
        </div>
        <button onClick={() => { setEditingGroupe(null); setShowModal(true); }} className="btn btn-primary gap-2">➕ Nouveau groupe</button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1"><SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher un groupe..." /></div>
          <FilterBar filters={FILTERS} values={filters} onChange={setFilters} />
        </div>

        <DataTable columns={COLUMNS} data={data?.data || []} isLoading={isLoading}
                   sortKey={sort.key} sortDir={sort.dir} onSort={k => setSort(s => s.key === k ? { key: k, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key: k, dir: 'asc' })}
                   actions={actions} emptyMessage="Aucun groupe trouvé" />

        <Pagination currentPage={page} lastPage={data?.meta?.last_page || 1} total={data?.meta?.total || 0} perPage={perPage} onPageChange={setPage} />
      </div>

      {showModal && (
        <GroupeModal groupe={editingGroupe} matieres={matieres} enseignants={enseignants}
                     onClose={() => { setShowModal(false); setEditingGroupe(null); }}
                     onSuccess={() => { setShowModal(false); setEditingGroupe(null); setRefreshKey(k => k + 1); }} />
      )}

      {detailGroupe && <GroupeDetailDrawer groupe={detailGroupe} onClose={() => setDetailGroupe(null)} />}
    </div>
  );
}

function GroupeModal({ groupe, matieres, enseignants, onClose, onSuccess }) {
  const [form, setForm] = useState({
    nom: groupe?.nom || '',
    matiere_id: groupe?.matiere_id || '',
    enseignant_id: groupe?.enseignant_id || '',
    niveau: groupe?.niveau || '',
    capacite_max: groupe?.capacite_max || 15,
    couleur: groupe?.couleur || '#1E5EBC',
    statut: groupe?.statut || 'actif',
    description: groupe?.description || '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const isEdit = !!groupe;

  const submit = async () => {
    setIsLoading(true);
    try {
      if (isEdit) { await api.put(`/groupes/${groupe.id}`, form); toast.success('Groupe mis à jour'); }
      else { await api.post('/groupes', form); toast.success('Groupe créé 🎉'); }
      onSuccess();
    } catch (err) { toast.error(err?.error?.message || 'Erreur'); }
    finally { setIsLoading(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md p-5 animate-slide-up">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">{isEdit ? '✏️ Modifier le groupe' : '➕ Nouveau groupe'}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg">✕</button>
        </div>
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div className="col-span-2">
              <label className="label">Nom du groupe *</label>
              <input value={form.nom} onChange={e => setForm(f => ({...f, nom: e.target.value}))} className="input" placeholder="Ex: Maths 1AS G1" />
            </div>
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
              <label className="label">Niveau *</label>
              <select value={form.niveau} onChange={e => setForm(f => ({...f, niveau: e.target.value}))} className="input">
                <option value="">—</option>
                {['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'].map(n => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="label">Enseignant</label>
            <select value={form.enseignant_id} onChange={e => setForm(f => ({...f, enseignant_id: e.target.value}))} className="input">
              <option value="">Non assigné</option>
              {enseignants.map(e => <option key={e.id} value={e.id}>{e.nom} {e.prenom} — {e.matieres?.map(m => m.nom_fr).join(', ')}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Capacité max</label>
              <input type="number" min={1} max={50} value={form.capacite_max} onChange={e => setForm(f => ({...f, capacite_max: Number(e.target.value)}))} className="input" />
            </div>
            <div>
              <label className="label">Couleur</label>
              <input type="color" value={form.couleur} onChange={e => setForm(f => ({...f, couleur: e.target.value}))} className="input h-[42px] p-1" />
            </div>
          </div>
          <div>
            <label className="label">Description (optionnelle)</label>
            <textarea value={form.description} onChange={e => setForm(f => ({...f, description: e.target.value}))} className="input resize-none h-20" placeholder="Description du groupe..." />
          </div>
          <button onClick={submit} disabled={isLoading || !form.nom || !form.matiere_id || !form.niveau} className="btn btn-primary w-full">
            {isLoading ? '⏳ Enregistrement...' : isEdit ? '💾 Enregistrer' : '✅ Créer le groupe'}
          </button>
        </div>
      </div>
    </div>
  );
}

function GroupeDetailDrawer({ groupe, onClose }) {
  const [eleves, setEleves] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  React.useEffect(() => {
    if (!groupe) return;
    api.get(`/groupes/${groupe.id}`).then(r => setEleves(r.data?.eleves || [])).finally(() => setIsLoading(false));
  }, [groupe?.id]);

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/30" onClick={onClose} />
      <div className="fixed right-0 top-0 h-full z-50 w-full sm:w-[440px] bg-white shadow-2xl flex flex-col animate-slide-left">
        <div className="bg-gradient-to-r from-primary-700 to-primary-500 p-5">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">👥</div>
              <div>
                <h2 className="text-xl font-bold text-white">{groupe.nom}</h2>
                <p className="text-primary-100 text-sm">{groupe.matiere?.nom_fr} • {groupe.niveau}</p>
              </div>
            </div>
            <button onClick={onClose} className="text-white p-2 hover:bg-white/20 rounded-lg">✕</button>
          </div>
          <div className="flex gap-4 mt-4">
            {[
              { label: 'Capacité', value: `${groupe.eleves_count || 0}/${groupe.capacite_max}`, color: 'bg-white/20' },
              { label: 'Statut', value: groupe.statut, color: groupe.statut === 'actif' ? 'bg-green-500/30' : 'bg-neutral-500/30' },
            ].map(s => (
              <div key={s.label} className="px-3 py-1.5 rounded-lg text-white text-sm" style={{ backgroundColor: s.color }}>
                <div className="text-xs text-white/70">{s.label}</div>
                <div className="font-semibold">{s.value}</div>
              </div>
            ))}
          </div>
        </div>
        <div className="flex-1 overflow-y-auto p-5">
          {groupe.enseignant && (
            <section className="mb-5">
              <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Enseignant</h3>
              <div className="p-3 bg-neutral-50 rounded-xl">
                <div className="font-semibold text-sm">{groupe.enseignant.nom} {groupe.enseignant.prenom}</div>
                <div className="text-xs text-neutral-400">{groupe.enseignant.email}</div>
              </div>
            </section>
          )}
          <section>
            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Élèves inscrits ({eleves.length})</h3>
            {isLoading ? <div className="flex justify-center py-6"><div className="animate-spin">⏳</div></div>
              : eleves.length === 0 ? <p className="text-sm text-neutral-400 italic">Aucun élève inscrit</p>
              : <div className="space-y-1.5">{eleves.map(e => (
                  <div key={e.id} className="flex items-center gap-2 p-2.5 bg-neutral-50 rounded-lg">
                    <div className="w-7 h-7 rounded-lg bg-primary-100 flex items-center justify-center text-primary-700 text-xs font-bold">
                      {e.nom?.[0]}{e.prenom?.[0]}
                    </div>
                    <div className="flex-1 text-sm font-medium">{e.nom} {e.prenom}</div>
                    <span className="text-xs text-neutral-400">{e.niveau_scolaire}</span>
                  </div>
              ))}</div>}
          </section>
        </div>
      </div>
    </>
  );
}
