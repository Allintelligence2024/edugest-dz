import React, { useState, useEffect, useCallback } from 'react';
import { tenantApi } from '@api/tenant.api';
import toast from 'react-hot-toast';

export default function SuperAdminPage() {
  const [tenants, setTenants] = useState([]);
  const [stats, setStats] = useState({});
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ nom_etablissement: '', slug: '', type_etablissement: 'centre', email: '', telephone: '', plan_abonnement: 'gratuit', admin_nom: '', admin_prenom: '', admin_email: '', admin_password: '' });

  const fetchData = useCallback(async () => {
    try { const r = await tenantApi.list({ per_page: 50 }); setTenants(r.data || []); } catch { /* ignore */ }
    try { const s = await tenantApi.stats(); setStats(s.data || {}); } catch { /* ignore */ }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleCreate = async (e) => {
    e.preventDefault();
    try { await tenantApi.create(form); toast.success('Établissement créé'); setShowForm(false); fetchData(); } catch { toast.error('Erreur création'); }
  };

  const handleSuspendre = async (id, statut) => {
    try { await tenantApi.update(id, { statut }); toast.success('Statut mis à jour'); fetchData(); } catch { toast.error('Erreur'); }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-neutral-800">SuperAdmin — Gestion des établissements</h1>

      <div className="grid grid-cols-4 gap-4">
        {[
          { label: 'Total', value: stats.total_tenants, color: 'text-primary-700' },
          { label: 'Actifs', value: stats.tenants_actifs, color: 'text-green-600' },
          { label: 'Expirés', value: stats.tenants_expires, color: 'text-red-600' },
          { label: 'Revenus estimés', value: `${stats.revenus_estimes || 0} DA`, color: 'text-amber-600' },
        ].map(kpi => (
          <div key={kpi.label} className="bg-white rounded-2xl p-5 border shadow-sm">
            <p className="text-sm text-neutral-500 mb-1">{kpi.label}</p>
            <p className={`text-2xl font-bold ${kpi.color}`}>{kpi.value}</p>
          </div>
        ))}
      </div>

      <div className="flex justify-end">
        <button onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium">{showForm ? 'Annuler' : 'Nouvel établissement'}</button>
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white rounded-2xl border p-6 grid grid-cols-2 gap-4">
          <input placeholder="Nom de l'établissement" value={form.nom_etablissement} onChange={e => setForm(f => ({ ...f, nom_etablissement: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <input placeholder="Slug (ex: mon-centre)" value={form.slug} onChange={e => setForm(f => ({ ...f, slug: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <select value={form.type_etablissement} onChange={e => setForm(f => ({ ...f, type_etablissement: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm bg-white"><option value="centre">Centre</option><option value="ecole">École</option><option value="institut">Institut</option></select>
          <input placeholder="Email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <input placeholder="Téléphone" value={form.telephone} onChange={e => setForm(f => ({ ...f, telephone: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <select value={form.plan_abonnement} onChange={e => setForm(f => ({ ...f, plan_abonnement: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm bg-white"><option value="gratuit">Gratuit</option><option value="premium">Premium</option></select>
          <input placeholder="Nom admin" value={form.admin_nom} onChange={e => setForm(f => ({ ...f, admin_nom: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <input placeholder="Prénom admin" value={form.admin_prenom} onChange={e => setForm(f => ({ ...f, admin_prenom: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <input placeholder="Email admin" value={form.admin_email} onChange={e => setForm(f => ({ ...f, admin_email: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <input placeholder="Mot de passe admin" value={form.admin_password} onChange={e => setForm(f => ({ ...f, admin_password: e.target.value }))} className="px-4 py-2.5 border rounded-xl text-sm" required />
          <div className="col-span-2"><button type="submit" className="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium">Créer l'établissement</button></div>
        </form>
      )}

      <div className="bg-white rounded-2xl border shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-neutral-50"><th className="text-left px-4 py-3">Établissement</th><th className="text-left px-4 py-3">Plan</th><th className="text-center px-4 py-3">Statut</th><th className="text-center px-4 py-3">Expiration</th><th className="text-right px-4 py-3">Utilisateurs</th><th className="text-right px-4 py-3">Élèves</th><th className="text-center px-4 py-3">Actions</th></tr></thead>
          <tbody>
            {tenants.map(t => (
              <tr key={t.id} className="border-b hover:bg-neutral-50">
                <td className="px-4 py-3 font-medium">{t.nom_etablissement}</td>
                <td className="px-4 py-3"><span className={`px-2 py-0.5 rounded text-xs font-medium ${t.plan_abonnement === 'premium' ? 'bg-amber-100 text-amber-700' : 'bg-neutral-100 text-neutral-600'}`}>{t.plan_abonnement}</span></td>
                <td className="px-4 py-3 text-center"><span className={`px-2.5 py-1 rounded-full text-xs font-medium ${t.statut === 'actif' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>{t.statut}</span></td>
                <td className="px-4 py-3 text-center text-neutral-500">{t.date_expiration ? new Date(t.date_expiration).toLocaleDateString('fr-DZ') : '-'}</td>
                <td className="px-4 py-3 text-right">{t.users_count}</td>
                <td className="px-4 py-3 text-right">{t.eleves_count}</td>
                <td className="px-4 py-3 text-center">
                  <div className="flex items-center justify-center gap-2">
                    {t.statut === 'actif' ? <button onClick={() => handleSuspendre(t.id, 'suspendu')} className="px-2 py-1 text-xs bg-red-50 text-red-600 rounded-lg">Suspendre</button>
                    : <button onClick={() => handleSuspendre(t.id, 'actif')} className="px-2 py-1 text-xs bg-green-50 text-green-600 rounded-lg">Réactiver</button>}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
