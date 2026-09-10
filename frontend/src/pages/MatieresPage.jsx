import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';

const COLUMNS = [
  { key: 'couleur', label: '', render: v => <div className="w-5 h-5 rounded-lg" style={{ backgroundColor: v || '#1E5EBC' }} /> },
  { key: 'nom_fr', label: 'Matière (FR)', sortable: true, render: (v, r) => <span className="font-semibold">{v}</span> },
  { key: 'nom_ar', label: 'المادة (AR)', render: v => v ? <span className="text-sm" dir="rtl">{v}</span> : <span className="text-sm text-neutral-400 italic">—</span> },
  { key: 'code', label: 'Code', render: v => v ? <span className="font-mono text-xs text-primary-600">{v}</span> : <span className="text-sm text-neutral-400 italic">—</span> },
  { key: 'groupes_count', label: 'Groupes', render: v => <span className="badge badge-neutral">{v || 0}</span> },
  { key: 'statut', label: 'Statut', render: v => {
    const styles = { active: 'badge-actif', inactive: 'badge-inactif' };
    return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v || 'active'}</span>;
  }},
];

export default function MatieresPage() {
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState({ key: 'nom_fr', dir: 'asc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(50);
  const [refreshKey, setRefreshKey] = useState(0);
  const [showModal, setShowModal] = useState(false);
  const [editingMatiere, setEditingMatiere] = useState(null);

  const query = new URLSearchParams({ search, page, per_page: perPage, sort_by: sort.key, sort_dir: sort.dir }).toString();
  const { data, isLoading } = useList('/matieres', query, 'matieres', refreshKey);

  const handleDelete = useCallback(async (matiere) => {
    if (!window.confirm(`Supprimer la matière "${matiere.nom_fr}" ?`)) return;
    try { await api.delete(`/matieres/${matiere.id}`); toast.success('Matière supprimée'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleSort = useCallback((key) => {
    setSort(s => s.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' });
    setPage(1);
  }, []);

  const actions = (row) => (
    <div className="flex items-center gap-1">
      <button onClick={() => { setEditingMatiere(row); setShowModal(true); }} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Modifier">✏️</button>
      <button onClick={() => handleDelete(row)} className="p-1.5 hover:bg-red-50 rounded-lg text-sm text-red-500" title="Supprimer">🗑️</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">📚 Matières</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Gérez les matières enseignées dans votre établissement</p>
        </div>
        <button onClick={() => { setEditingMatiere(null); setShowModal(true); }} className="btn btn-primary gap-2">
          ➕ Nouvelle matière
        </button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1">
            <SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher une matière..." />
          </div>
        </div>

        <DataTable
          columns={COLUMNS}
          data={data?.data || []}
          isLoading={isLoading}
          sortKey={sort.key}
          sortDir={sort.dir}
          onSort={handleSort}
          actions={actions}
          emptyMessage="Aucune matière trouvée" />

        <Pagination
          currentPage={page}
          lastPage={data?.meta?.last_page || 1}
          total={data?.meta?.total || 0}
          perPage={perPage}
          onPageChange={setPage} />
      </div>

      {showModal && (
        <MatiereModal
          matiere={editingMatiere}
          onClose={() => { setShowModal(false); setEditingMatiere(null); }}
          onSuccess={() => { setShowModal(false); setEditingMatiere(null); setRefreshKey(k => k + 1); }} />
      )}
    </div>
  );
}

function MatiereModal({ matiere, onClose, onSuccess }) {
  const [form, setForm] = useState({
    nom_fr: matiere?.nom_fr || '',
    nom_ar: matiere?.nom_ar || '',
    code: matiere?.code || '',
    couleur: matiere?.couleur || '#1E5EBC',
    description: matiere?.description || '',
    statut: matiere?.statut || 'active',
  });
  const [isLoading, setIsLoading] = useState(false);
  const isEdit = !!matiere;

  const submit = async () => {
    setIsLoading(true);
    try {
      if (isEdit) { await api.put(`/matieres/${matiere.id}`, form); toast.success('Matière mise à jour'); }
      else { await api.post('/matieres', form); toast.success('Matière créée 🎉'); }
      onSuccess();
    } catch (err) { toast.error(err?.error?.message || 'Erreur'); }
    finally { setIsLoading(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md p-5 animate-slide-up">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">{isEdit ? '✏️ Modifier la matière' : '➕ Nouvelle matière'}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg">✕</button>
        </div>
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Nom (français) *</label>
              <input value={form.nom_fr} onChange={e => setForm(f => ({...f, nom_fr: e.target.value}))} className="input" placeholder="Mathématiques" />
            </div>
            <div>
              <label className="label">Nom (arabe)</label>
              <input value={form.nom_ar} onChange={e => setForm(f => ({...f, nom_ar: e.target.value}))} className="input" placeholder="الرياضيات" dir="rtl" />
            </div>
          </div>
          <div>
            <label className="label">Code</label>
            <input value={form.code} onChange={e => setForm(f => ({...f, code: e.target.value}))} className="input" placeholder="MATH01" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Couleur</label>
              <input type="color" value={form.couleur} onChange={e => setForm(f => ({...f, couleur: e.target.value}))} className="input h-[42px] p-1" />
            </div>
            <div>
              <label className="label">Statut</label>
              <select value={form.statut} onChange={e => setForm(f => ({...f, statut: e.target.value}))} className="input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div>
            <label className="label">Description</label>
            <textarea value={form.description} onChange={e => setForm(f => ({...f, description: e.target.value}))} className="input resize-none h-20" placeholder="Description de la matière..." />
          </div>
          <button onClick={submit} disabled={isLoading || !form.nom_fr} className="btn btn-primary w-full">
            {isLoading ? '⏳ Enregistrement...' : isEdit ? '💾 Enregistrer' : '✅ Créer la matière'}
          </button>
        </div>
      </div>
    </div>
  );
}
