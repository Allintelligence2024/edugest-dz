import React, { useState, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import { useList } from '@hooks/useApi';
import SearchBar from '@components/common/SearchBar';
import FilterBar from '@components/common/FilterBar';
import DataTable from '@components/common/DataTable';
import Pagination from '@components/common/Pagination';

const FILTERS = [
  { key: 'statut', label: 'Statut', options: [{value:'actif',label:'Actif'},{value:'inactif',label:'Inactif'}] },
  { key: 'type_contrat', label: 'Contrat', options: [{value:'CDI',label:'CDI'},{value:'CDD',label:'CDD'},{value:'vacataire',label:'Vacataire'}] },
  { key: 'wilaya_id', label: 'Wilaya', options: [] },
];

const COLUMNS = [
  { key: 'photo_url', label: '', render: (v, r) => v
    ? <img src={v} className="w-9 h-9 rounded-lg object-cover" alt="" />
    : <div className="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center text-primary-600 font-bold">{r.prenom?.[0]}{r.nom?.[0]}</div> },
  { key: 'matricule', label: 'Matricule', render: v => <span className="font-mono text-xs text-primary-600">{v}</span> },
  { key: 'nom', label: 'Nom & Prénom', sortable: true, render: (v, r) => (
    <div>
      <span className="font-semibold">{v} {r.prenom}</span>
      {r.nom_ar && <span className="text-xs text-neutral-400 block" dir="rtl">{r.nom_ar} {r.prenom_ar}</span>}
    </div>
  )},
  { key: 'telephone', label: 'Téléphone' },
  { key: 'email', label: 'Email', render: v => <span className="text-sm text-neutral-600">{v}</span> },
  { key: 'type_contrat', label: 'Contrat', render: v => {
    const styles = { CDI: 'badge-actif', CDD: 'badge-neutral', vacataire: 'badge-warning' };
    return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v}</span>;
  }},
  { key: 'statut', label: 'Statut', sortable: true, render: v => {
    const styles = { actif: 'badge-actif', inactif: 'badge-inactif' };
    return <span className={`badge ${styles[v] || 'badge-neutral'}`}>{v || 'inconnu'}</span>;
  }},
];

export default function EnseignantsListPage() {
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [sort, setSort] = useState({ key: 'nom', dir: 'asc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [refreshKey, setRefreshKey] = useState(0);
  const [showModal, setShowModal] = useState(false);
  const [editingEnseignant, setEditingEnseignant] = useState(null);
  const [detailEnseignant, setDetailEnseignant] = useState(null);

  const query = new URLSearchParams({ search, page, per_page: perPage, sort_by: sort.key, sort_dir: sort.dir, ...filters }).toString();
  const { data, isLoading } = useList('/enseignants', query, 'enseignants', refreshKey);

  const handleDelete = useCallback(async (enseignant) => {
    if (!window.confirm(`Supprimer ${enseignant.nom} ${enseignant.prenom} ?`)) return;
    try { await api.delete(`/enseignants/${enseignant.id}`); toast.success('Enseignant supprimé'); setRefreshKey(k => k + 1); }
    catch (err) { toast.error(err?.error?.message || 'Erreur'); }
  }, []);

  const handleSort = useCallback((key) => {
    setSort(s => s.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' });
    setPage(1);
  }, []);

  const actions = (row) => (
    <div className="flex items-center gap-1">
      <button onClick={() => setDetailEnseignant(row)} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Détails">👁️</button>
      <button onClick={() => { setEditingEnseignant(row); setShowModal(true); }} className="p-1.5 hover:bg-neutral-100 rounded-lg text-sm" title="Modifier">✏️</button>
      <button onClick={() => handleDelete(row)} className="p-1.5 hover:bg-red-50 rounded-lg text-sm text-red-500" title="Supprimer">🗑️</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">👨‍🏫 Enseignants</h1>
          <p className="text-sm text-neutral-400 mt-0.5">Gérez les enseignants de votre établissement</p>
        </div>
        <button onClick={() => { setEditingEnseignant(null); setShowModal(true); }} className="btn btn-primary gap-2">
          ➕ Nouvel enseignant
        </button>
      </div>

      <div className="card p-4 space-y-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1">
            <SearchBar value={search} onChange={v => { setSearch(v); setPage(1); }} placeholder="Rechercher un enseignant..." />
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
          onRowClick={(row) => setDetailEnseignant(row)}
          actions={actions}
          emptyMessage="Aucun enseignant trouvé" />

        <Pagination
          currentPage={page}
          lastPage={data?.meta?.last_page || 1}
          total={data?.meta?.total || 0}
          perPage={perPage}
          onPageChange={setPage} />
      </div>

      {showModal && (
        <EnseignantModal
          enseignant={editingEnseignant}
          onClose={() => { setShowModal(false); setEditingEnseignant(null); }}
          onSuccess={() => { setShowModal(false); setEditingEnseignant(null); setRefreshKey(k => k + 1); }} />
      )}

      {detailEnseignant && (
        <EnseignantDetailDrawer
          enseignant={detailEnseignant}
          onClose={() => setDetailEnseignant(null)} />
      )}
    </div>
  );
}

function EnseignantModal({ enseignant, onClose, onSuccess }) {
  const [form, setForm] = useState({
    nom: enseignant?.nom || '',
    prenom: enseignant?.prenom || '',
    nom_ar: enseignant?.nom_ar || '',
    prenom_ar: enseignant?.prenom_ar || '',
    email: enseignant?.email || '',
    telephone: enseignant?.telephone || '',
    sexe: enseignant?.sexe || 'M',
    date_naissance: enseignant?.date_naissance || '',
    lieu_naissance: enseignant?.lieu_naissance || '',
    adresse: enseignant?.adresse || '',
    wilaya_id: enseignant?.wilaya_id || '',
    diplome: enseignant?.diplome || '',
    specialite: enseignant?.specialite || '',
    experience_annees: enseignant?.experience_annees || 0,
    type_contrat: enseignant?.type_contrat || 'vacataire',
    date_embauche: enseignant?.date_embauche || '',
    salaire_base: enseignant?.salaire_base || 0,
    taux_horaire: enseignant?.taux_horaire || 0,
    statut: enseignant?.statut || 'actif',
  });
  const [isLoading, setIsLoading] = useState(false);
  const isEdit = !!enseignant;

  const submit = async () => {
    setIsLoading(true);
    try {
      if (isEdit) { await api.put(`/enseignants/${enseignant.id}`, form); toast.success('Enseignant mis à jour'); }
      else { await api.post('/enseignants', form); toast.success('Enseignant créé 🎉'); }
      onSuccess();
    } catch (err) { toast.error(err?.error?.message || 'Erreur'); }
    finally { setIsLoading(false); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-2xl p-5 animate-slide-up max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">{isEdit ? '✏️ Modifier l\'enseignant' : '➕ Nouvel enseignant'}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg">✕</button>
        </div>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">Nom *</label>
              <input value={form.nom} onChange={e => setForm(f => ({...f, nom: e.target.value}))} className="input" placeholder="Nom" />
            </div>
            <div>
              <label className="label">Prénom *</label>
              <input value={form.prenom} onChange={e => setForm(f => ({...f, prenom: e.target.value}))} className="input" placeholder="Prénom" />
            </div>
            <div>
              <label className="label">Nom (arabe)</label>
              <input value={form.nom_ar} onChange={e => setForm(f => ({...f, nom_ar: e.target.value}))} className="input" placeholder="الاسم" dir="rtl" />
            </div>
            <div>
              <label className="label">Prénom (arabe)</label>
              <input value={form.prenom_ar} onChange={e => setForm(f => ({...f, prenom_ar: e.target.value}))} className="input" placeholder="اللقب" dir="rtl" />
            </div>
            <div>
              <label className="label">Email *</label>
              <input type="email" value={form.email} onChange={e => setForm(f => ({...f, email: e.target.value}))} className="input" placeholder="email@exemple.com" />
            </div>
            <div>
              <label className="label">Téléphone</label>
              <input value={form.telephone} onChange={e => setForm(f => ({...f, telephone: e.target.value}))} className="input" placeholder="05XX XX XX XX" />
            </div>
            <div>
              <label className="label">Sexe</label>
              <select value={form.sexe} onChange={e => setForm(f => ({...f, sexe: e.target.value}))} className="input">
                <option value="M">Masculin</option>
                <option value="F">Féminin</option>
              </select>
            </div>
            <div>
              <label className="label">Date de naissance</label>
              <input type="date" value={form.date_naissance} onChange={e => setForm(f => ({...f, date_naissance: e.target.value}))} className="input" />
            </div>
            <div>
              <label className="label">Lieu de naissance</label>
              <input value={form.lieu_naissance} onChange={e => setForm(f => ({...f, lieu_naissance: e.target.value}))} className="input" />
            </div>
            <div>
              <label className="label">Wilaya</label>
              <select value={form.wilaya_id} onChange={e => setForm(f => ({...f, wilaya_id: e.target.value}))} className="input">
                <option value="">—</option>
              </select>
            </div>
            <div className="col-span-2">
              <label className="label">Adresse</label>
              <input value={form.adresse} onChange={e => setForm(f => ({...f, adresse: e.target.value}))} className="input" placeholder="Adresse complète" />
            </div>
          </div>

          <hr className="border-neutral-200" />
          <h3 className="font-semibold text-sm text-neutral-700">Informations professionnelles</h3>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">Diplôme</label>
              <input value={form.diplome} onChange={e => setForm(f => ({...f, diplome: e.target.value}))} className="input" placeholder="Master, Licence..." />
            </div>
            <div>
              <label className="label">Spécialité</label>
              <input value={form.specialite} onChange={e => setForm(f => ({...f, specialite: e.target.value}))} className="input" placeholder="Mathématiques..." />
            </div>
            <div>
              <label className="label">Expérience (années)</label>
              <input type="number" min={0} value={form.experience_annees} onChange={e => setForm(f => ({...f, experience_annees: Number(e.target.value)}))} className="input" />
            </div>
            <div>
              <label className="label">Type de contrat *</label>
              <select value={form.type_contrat} onChange={e => setForm(f => ({...f, type_contrat: e.target.value}))} className="input">
                <option value="vacataire">Vacataire</option>
                <option value="CDI">CDI</option>
                <option value="CDD">CDD</option>
              </select>
            </div>
            <div>
              <label className="label">Date d'embauche</label>
              <input type="date" value={form.date_embauche} onChange={e => setForm(f => ({...f, date_embauche: e.target.value}))} className="input" />
            </div>
            <div>
              <label className="label">Salaire de base (DZD)</label>
              <input type="number" min={0} value={form.salaire_base} onChange={e => setForm(f => ({...f, salaire_base: Number(e.target.value)}))} className="input" />
            </div>
            <div>
              <label className="label">Taux horaire (DZD)</label>
              <input type="number" min={0} value={form.taux_horaire} onChange={e => setForm(f => ({...f, taux_horaire: Number(e.target.value)}))} className="input" />
            </div>
            <div>
              <label className="label">Statut</label>
              <select value={form.statut} onChange={e => setForm(f => ({...f, statut: e.target.value}))} className="input">
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
              </select>
            </div>
          </div>

          <button onClick={submit} disabled={isLoading || !form.nom || !form.prenom || !form.email || !form.type_contrat} className="btn btn-primary w-full">
            {isLoading ? '⏳ Enregistrement...' : isEdit ? '💾 Enregistrer' : '✅ Créer l\'enseignant'}
          </button>
        </div>
      </div>
    </div>
  );
}

function EnseignantDetailDrawer({ enseignant, onClose }) {
  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/30" onClick={onClose} />
      <div className="fixed right-0 top-0 h-full z-50 w-full sm:w-[440px] bg-white shadow-2xl flex flex-col animate-slide-left">
        <div className="bg-gradient-to-r from-primary-700 to-primary-500 p-5">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl">👨‍🏫</div>
              <div>
                <h2 className="text-xl font-bold text-white">{enseignant.nom} {enseignant.prenom}</h2>
                <p className="text-primary-100 text-sm">{enseignant.matricule} • {enseignant.type_contrat}</p>
              </div>
            </div>
            <button onClick={onClose} className="text-white p-2 hover:bg-white/20 rounded-lg">✕</button>
          </div>
          <div className="flex gap-4 mt-4">
            {[
              { label: 'Statut', value: enseignant.statut, color: enseignant.statut === 'actif' ? 'bg-green-500/30' : 'bg-neutral-500/30' },
              { label: 'Contrat', value: enseignant.type_contrat, color: 'bg-white/20' },
              { label: 'Salaire', value: enseignant.salaire_base ? `${enseignant.salaire_base.toLocaleString()} DA` : '—', color: 'bg-white/20' },
            ].map(s => (
              <div key={s.label} className="px-3 py-1.5 rounded-lg text-white text-sm" style={{ backgroundColor: s.color }}>
                <div className="text-xs text-white/70">{s.label}</div>
                <div className="font-semibold">{s.value}</div>
              </div>
            ))}
          </div>
        </div>
        <div className="flex-1 overflow-y-auto p-5 space-y-5">
          <section>
            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Contact</h3>
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-sm"><span className="text-neutral-400">📧</span> {enseignant.email}</div>
              <div className="flex items-center gap-2 text-sm"><span className="text-neutral-400">📞</span> {enseignant.telephone || '—'}</div>
              {enseignant.adresse && <div className="flex items-center gap-2 text-sm"><span className="text-neutral-400">📍</span> {enseignant.adresse}</div>}
            </div>
          </section>
          {enseignant.diplome && (
            <section>
              <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Formation</h3>
              <p className="text-sm">{enseignant.diplome}{enseignant.specialite ? ` — ${enseignant.specialite}` : ''}</p>
              {enseignant.experience_annees > 0 && <p className="text-xs text-neutral-400">{enseignant.experience_annees} ans d'expérience</p>}
            </section>
          )}
          {enseignant.matieres?.length > 0 && (
            <section>
              <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Matières enseignées</h3>
              <div className="flex flex-wrap gap-1.5">
                {enseignant.matieres.map(m => (
                  <span key={m.id} className="px-2.5 py-1 bg-primary-50 text-primary-700 rounded-lg text-xs font-medium">{m.nom_fr}</span>
                ))}
              </div>
            </section>
          )}
        </div>
      </div>
    </>
  );
}
