import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import FilterBar from '@components/common/FilterBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';

const FILTERS = [
  { key: 'statut', label: 'Statut', options: [{value:'disponible',label:'Disponible'},{value:'occupée',label:'Occupée'},{value:'maintenance',label:'Maintenance'}] },
];

const COLUMNS = [
  { key: 'nom', label: 'Salle', sortable: true, render: (v, r) => (
    <div className="flex items-center gap-3">
      <div className="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center text-primary-600 font-bold">🏫</div>
      <span className="font-semibold">{v}</span>
    </div>
  )},
  { key: 'capacite', label: 'Capacité', sortable: true, render: v => <span className="badge badge-neutral">{v} places</span> },
  { key: 'equipements', label: 'Équipements', render: v => {
    if (!v?.length) return <span className="text-sm text-neutral-400 italic">Aucun</span>;
    return <span className="text-sm">{v.slice(0, 3).join(', ')}{v.length > 3 ? '...' : ''}</span>;
  }},
  { key: 'localisation', label: 'Localisation', render: v => v ? <span className="text-sm">{v}</span> : <span className="text-sm text-neutral-400 italic">—</span> },
  { key: 'cours_count', label: 'Cours', render: v => <span className="text-sm font-medium">{v || 0}</span> },
  { key: 'statut', label: 'Statut', sortable: true, render: v => {
    const styles = { disponible: 'badge-actif', 'occupée': 'badge-warning', maintenance: 'badge-inactif' };
    return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v}</span>;
  }},
];

export default function SallesPage() {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [sort, setSort] = useState({ key: 'nom', dir: 'asc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [refreshKey, setRefreshKey] = useState(0);
  const [showModal, setShowModal] = useState(false);
  const [editingSalle, setEditingSalle] = useState(null);

  const query = new URLSearchParams({ search, page, per_page: perPage, sort_by: sort.key, sort_dir: sort.dir, ...filters }).toString();
  const { data, isLoading } = useList('/salles', query, 'salles', refreshKey);

  const handleDelete = useCallback(async (salle) => {
    if (!window.confirm(`Supprimer la salle "${salle.nom}" ?`)) return;
    try { await api.delete(`/salles/${salle.id}`); toast.success('Salle supprimée'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleSort = useCallback((key) => {
    setSort(s => s.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' });
    setPage(1);
  }, []);

  const actions = (row) => (
    <div className="flex items-center gap-1">
      <button onClick={() => { setEditingSalle(row); setShowModal(true); }} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Modifier">✏️</button>
      <button onClick={() => handleDelete(row)} className="p-1.5 hover:bg-red-50 rounded-lg text-sm text-red-500" title="Supprimer">🗑️</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">🏫 Salles</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Gérez les salles et leurs disponibilités</p>
        </div>
        <button onClick={() => { setEditingSalle(null); setShowModal(true); }} className="btn btn-primary gap-2">
          ➕ Nouvelle salle
        </button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1">
            <SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher une salle..." />
          </div>
          <FilterBar filters={FILTERS} values={filters} onChange={setFilters} />
        </div>

        <DataTable
          columns={COLUMNS}
          data={data?.data || []}
          isLoading={isLoading}
          sortKey={sort.key}
          sortDir={sort.dir}
          onSort={handleSort}
          actions={actions}
          emptyMessage="Aucune salle trouvée" />

        <Pagination
          currentPage={page}
          lastPage={data?.meta?.last_page || 1}
          total={data?.meta?.total || 0}
          perPage={perPage}
          onPageChange={setPage} />
      </div>

      {showModal && (
        <SalleModal
          salle={editingSalle}
          onClose={() => { setShowModal(false); setEditingSalle(null); }}
          onSuccess={() => { setShowModal(false); setEditingSalle(null); setRefreshKey(k => k + 1); }} />
      )}
    </div>
  );
}

function SalleModal({ salle, onClose, onSuccess }) {
  const [form, setForm] = useState({
    nom: salle?.nom || '',
    capacite: salle?.capacite || 20,
    equipements: salle?.equipements?.join(', ') || '',
    localisation: salle?.localisation || '',
    statut: salle?.statut || 'disponible',
  });
  const [isLoading, setIsLoading] = useState(false);
  const isEdit = !!salle;

  const submit = async () => {
    setIsLoading(true);
    const payload = {
      ...form,
      equipements: form.equipements ? form.equipements.split(',').map(s => s.trim()).filter(Boolean) : [],
    };
    try {
      if (isEdit) { await api.put(`/salles/${salle.id}`, payload); toast.success('Salle mise à jour'); }
      else { await api.post('/salles', payload); toast.success('Salle créée 🎉'); }
      onSuccess();
    } catch (err) { toast.error(err?.error?.message || 'Erreur'); }
    finally { setIsLoading(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md p-5 animate-slide-up">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">{isEdit ? '✏️ Modifier la salle' : '➕ Nouvelle salle'}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg">✕</button>
        </div>
        <div className="space-y-3">
          <div>
            <label className="label">Nom de la salle *</label>
            <input value={form.nom} onChange={e => setForm(f => ({...f, nom: e.target.value}))} className="input" placeholder="Ex: Salle A1" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Capacité *</label>
              <input type="number" min={1} max={100} value={form.capacite} onChange={e => setForm(f => ({...f, capacite: Number(e.target.value)}))} className="input" />
            </div>
            <div>
              <label className="label">Statut</label>
              <select value={form.statut} onChange={e => setForm(f => ({...f, statut: e.target.value}))} className="input">
                <option value="disponible">Disponible</option>
                <option value="occupée">Occupée</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
          </div>
          <div>
            <label className="label">Localisation</label>
            <input value={form.localisation} onChange={e => setForm(f => ({...f, localisation: e.target.value}))} className="input" placeholder="Ex: Rez-de-chaussée, aile B" />
          </div>
          <div>
            <label className="label">Équipements (séparés par des virgules)</label>
            <input value={form.equipements} onChange={e => setForm(f => ({...f, equipements: e.target.value}))} className="input" placeholder="Tableau blanc, Projecteur, Climatisation..." />
          </div>
          <button onClick={submit} disabled={isLoading || !form.nom || !form.capacite} className="btn btn-primary w-full">
            {isLoading ? '⏳ Enregistrement...' : isEdit ? '💾 Enregistrer' : '✅ Créer la salle'}
          </button>
        </div>
      </div>
    </div>
  );
}
