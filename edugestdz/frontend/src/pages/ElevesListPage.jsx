import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import FilterBar from '@components/common/FilterBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';
import EleveModal from '@components/eleves/EleveModal';
import EleveDetailDrawer from '@components/eleves/EleveDetailDrawer';

const FILTERS = [
  { key: 'niveau_scolaire', label: 'Niveau', options: ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire','autre'] },
  { key: 'statut', label: 'Statut', options: ['actif','inactif','suspendu'] },
  { key: 'sexe', label: 'Sexe', options: [{ value: 'M', label: 'Masculin' }, { value: 'F', label: 'Féminin' }] },
];

const COLUMNS = [
  { key: 'photo_url', label: '', render: (v, r) => v
    ? <img src={v} className="w-9 h-9 rounded-lg object-cover" alt="" />
    : <div className="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center text-primary-600 font-bold">{r.prenom?.[0]}{r.nom?.[0]}</div> },
  { key: 'numero_inscription', label: 'N° Insc.', render: v => <span className="font-mono text-xs text-primary-600">{v}</span> },
  { key: 'nom', label: 'Nom', sortable: true, render: (v, r) => <span className="font-semibold">{v} {r.prenom}</span> },
  { key: 'niveau_scolaire', label: 'Niveau', render: v => <span className="badge badge-primary">{v}</span> },
  { key: 'parents_count', label: 'Contacts', render: (v, r) => <span className="text-sm">{v || 0}</span> },
  { key: 'statut', label: 'Statut', sortable: true, render: v => {
    const styles = { actif: 'badge-actif', inactif: 'badge-inactif', suspendu: 'badge-warning' };
    return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v || 'inconnu'}</span>;
  }},
  { key: 'created_at', label: 'Inscription', render: v => <span className="text-sm text-neutral-400">{new Date(v).toLocaleDateString('fr-DZ')}</span> },
];

export default function ElevesListPage() {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [sort, setSort] = useState({ key: 'created_at', dir: 'desc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [selectedEleve, setSelectedEleve] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [editingEleve, setEditingEleve] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);

  const query = new URLSearchParams({ search, page, per_page: perPage, sort_by: sort.key, sort_dir: sort.dir, ...filters }).toString();

  const { data, isLoading } = useList('/eleves', query, 'eleves', refreshKey);

  const handleDelete = useCallback(async (eleve) => {
    if (!window.confirm(`Supprimer ${eleve.nom} ${eleve.prenom} ?`)) return;
    try { await api.delete(`/eleves/${eleve.id}`); toast.success('Élève supprimé'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleToggleStatut = useCallback(async (eleve) => {
    try { await api.post(`/eleves/${eleve.id}/toggle-statut`); toast.success(`Statut changé`); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleSort = useCallback((key) => {
    setSort(s => s.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' });
    setPage(1);
  }, []);

  const actions = (row) => (
    <div className="flex items-center gap-1">
      <button onClick={() => setSelectedEleve(row)} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Détails">👁️</button>
      <button onClick={() => { setEditingEleve(row); setShowModal(true); }} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Modifier">✏️</button>
      <button onClick={() => handleToggleStatut(row)} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Changer statut">🔄</button>
      <button onClick={() => handleDelete(row)} className="p-1.5 hover:bg-red-50 rounded-lg text-sm text-red-500" title="Supprimer">🗑️</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">👨‍🎓 Élèves</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Gérez les élèves inscrits dans votre établissement</p>
        </div>
        <button onClick={() => { setEditingEleve(null); setShowModal(true); }} className="btn btn-primary gap-2">
          ➕ Nouvel élève
        </button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1">
            <SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher un élève..." />
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
          onRowClick={(row) => setSelectedEleve(row)}
          actions={actions}
          emptyMessage="Aucun élève trouvé" />

        <Pagination
          currentPage={page}
          lastPage={data?.meta?.last_page || 1}
          total={data?.meta?.total || 0}
          perPage={perPage}
          onPageChange={setPage} />
      </div>

      <EleveDetailDrawer
        isOpen={!!selectedEleve}
        eleve={selectedEleve}
        onClose={() => setSelectedEleve(null)}
        onEdit={() => { setEditingEleve(selectedEleve); setSelectedEleve(null); setShowModal(true); }} />

      <EleveModal
        isOpen={showModal}
        eleve={editingEleve}
        onClose={() => { setShowModal(false); setEditingEleve(null); }}
        onSuccess={() => { setShowModal(false); setEditingEleve(null); setRefreshKey(k => k + 1); }} />
    </div>
  );
}
