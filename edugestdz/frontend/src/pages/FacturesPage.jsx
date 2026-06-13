import React, { useState, useEffect } from 'react';
import api from '@api/axiosInstance';
import FactureModal from '@components/finance/FactureModal';
import PaiementModal from '@components/finance/PaiementModal';

const STATUT_BADGE = {
  émise:          { bg: '#EEF4FF', text: '#1E5EBC' },
  envoyée:        { bg: '#FFF8EC', text: '#F39C12' },
  partiellement_payée: { bg: '#FEF3E2', text: '#E08E0B' },
  payée:          { bg: '#EDFAF3', text: '#27AE60' },
  impayée:        { bg: '#FDECEA', text: '#E74C3C' },
  annulée:        { bg: '#F5F5F5', text: '#95A5A6' },
  remboursée:     { bg: '#F0E6FF', text: '#8E44AD' },
};

export default function FacturesPage() {
  const [factures, setFactures] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showFactureModal, setShowFactureModal] = useState(false);
  const [paiementFacture, setPaiementFacture] = useState(null);
  const [total, setTotal] = useState({ montant_total: 0, montant_paye: 0, montant_impaye: 0 });

  const load = async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/factures', { params: { per_page: 50 } });
      setFactures(res.data || []);
      setTotal(res.synthese || { montant_total: 0, montant_paye: 0, montant_impaye: 0 });
    } catch { /* silent */ }
    finally { setIsLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const statutBadge = (statut) => {
    const c = STATUT_BADGE[statut] || STATUT_BADGE.impayée;
    return (
      <span className="inline-block px-2.5 py-1 rounded-lg text-xs font-bold" style={{ backgroundColor: c.bg, color: c.text }}>
        {statut?.replace('_', ' ')}
      </span>
    );
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-neutral-800">💰 Facturation</h1>
          <p className="text-neutral-500 text-sm mt-1">Gérez les factures et paiements</p>
        </div>
        <button onClick={() => setShowFactureModal(true)} className="px-5 py-2.5 bg-primary-600 text-white rounded-xl font-semibold text-sm hover:bg-primary-700 transition-colors">
          ➕ Nouvelle facture
        </button>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white rounded-2xl border border-neutral-200 p-4">
          <p className="text-xs font-semibold text-neutral-500 uppercase">Total facturé</p>
          <p className="text-2xl font-bold text-neutral-800 mt-1">{Number(total.montant_total).toLocaleString()} DA</p>
        </div>
        <div className="bg-white rounded-2xl border border-green-200 p-4">
          <p className="text-xs font-semibold text-green-600 uppercase">Payé</p>
          <p className="text-2xl font-bold text-green-700 mt-1">{Number(total.montant_paye).toLocaleString()} DA</p>
        </div>
        <div className="bg-white rounded-2xl border border-red-200 p-4">
          <p className="text-xs font-semibold text-red-600 uppercase">Impayé</p>
          <p className="text-2xl font-bold text-red-700 mt-1">{Number(total.montant_impaye).toLocaleString()} DA</p>
        </div>
      </div>

      <div className="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left p-4 font-semibold text-neutral-600">Facture</th>
              <th className="text-left p-4 font-semibold text-neutral-600">Élève</th>
              <th className="text-left p-4 font-semibold text-neutral-600">Date</th>
              <th className="text-left p-4 font-semibold text-neutral-600">Échéance</th>
              <th className="text-right p-4 font-semibold text-neutral-600">Montant</th>
              <th className="text-center p-4 font-semibold text-neutral-600">Statut</th>
              <th className="text-center p-4 font-semibold text-neutral-600">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={7} className="text-center p-8 text-neutral-400">Chargement...</td></tr>
            ) : factures.length === 0 ? (
              <tr><td colSpan={7} className="text-center p-8 text-neutral-400">Aucune facture</td></tr>
            ) : factures.map(f => (
              <tr key={f.id} className="hover:bg-neutral-50 transition-colors">
                <td className="p-4 font-mono text-xs font-bold text-neutral-700">{f.numero_facture}</td>
                <td className="p-4 text-neutral-700">{f.eleve_nom} {f.eleve_prenom}</td>
                <td className="p-4 text-neutral-500">{f.date_emission?.substring(0, 10)}</td>
                <td className="p-4 text-neutral-500">{f.date_echeance?.substring(0, 10)}</td>
                <td className="p-4 text-right font-bold text-neutral-800">{Number(f.total_ttc).toLocaleString()} DA</td>
                <td className="p-4 text-center">{statutBadge(f.statut)}</td>
                <td className="p-4 text-center">
                  <div className="flex items-center justify-center gap-2">
                    <button onClick={() => window.open(`/api/v1/factures/${f.id}/pdf`, '_blank')} className="px-2 py-1 text-xs bg-neutral-100 text-neutral-600 rounded-lg hover:bg-neutral-200">PDF</button>
                    <button onClick={() => setPaiementFacture(f)} className="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-lg hover:bg-green-200 font-semibold" disabled={f.statut === 'payée' || f.statut === 'remboursée' || f.statut === 'annulée'}>Paiement</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <FactureModal isOpen={showFactureModal} onClose={() => setShowFactureModal(false)} onSuccess={() => { setShowFactureModal(false); load(); }} />
      <PaiementModal isOpen={!!paiementFacture} facture={paiementFacture} onClose={() => setPaiementFacture(null)} onSuccess={() => { setPaiementFacture(null); load(); }} />
    </div>
  );
}
