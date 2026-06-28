import React, { useState, useCallback } from 'react';
import { paieApi } from '@api/paie.api';
import PaieDetailDrawer from '@components/personnel/PaieDetailDrawer';
import toast from 'react-hot-toast';

const MOIS = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

const STATUT_STYLES = {
  brouillon: 'bg-neutral-100 text-neutral-600',
  calculée: 'bg-blue-100 text-blue-700',
  validée: 'bg-amber-100 text-amber-700',
  payée: 'bg-green-100 text-green-700',
  annulée: 'bg-red-100 text-red-600',
};

function StatutBadge({ statut }) {
  return (
    <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${STATUT_STYLES[statut] || ''}`}>
      {statut}
    </span>
  );
}

export default function PaiesPage() {
  const today = new Date();
  const [mois, setMois] = useState(today.getMonth() + 1);
  const [annee, setAnnee] = useState(today.getFullYear());
  const [paies, setPaies] = useState([]);
  const [loading, setLoading] = useState(false);
  const [calculLoading, setCalculLoading] = useState(false);
  const [selectedPaie, setSelectedPaie] = useState(null);

  const fetchPaies = useCallback(async () => {
    setLoading(true);
    try {
      const res = await paieApi.list({ mois, annee, per_page: 50 });
      setPaies(res.data?.data || []);
    } catch {
      toast.error('Erreur chargement des paies');
    } finally {
      setLoading(false);
    }
  }, [mois, annee]);

  const handleCalculer = async () => {
    setCalculLoading(true);
    try {
      await paieApi.calculer({ mois, annee });
      toast.success(`Paies de ${MOIS[mois - 1]} ${annee} calculées`);
      fetchPaies();
    } catch (e) {
      toast.error(e?.error?.message || 'Erreur calcul');
    } finally {
      setCalculLoading(false);
    }
  };

  const handleValider = async (id) => {
    try {
      await paieApi.valider(id);
      toast.success('Paie validée');
      fetchPaies();
    } catch (e) {
      toast.error(e?.error?.message || 'Erreur validation');
    }
  };

  const handlePayer = async (id) => {
    try {
      await paieApi.payer(id, { mode_paiement: 'virement' });
      toast.success('Paie effectuée');
      fetchPaies();
    } catch (e) {
      toast.error(e?.error?.message || 'Erreur paiement');
    }
  };

  const totaux = paies.reduce(
    (acc, p) => ({
      brut: acc.brut + Number(p.salaire_base || 0),
      irg: acc.irg + Number(p.irg || 0),
      cnas: acc.cnas + Number(p.cnas || 0),
      net: acc.net + Number(p.salaire_net || 0),
    }),
    { brut: 0, irg: 0, cnas: 0, net: 0 },
  );

  return (
    <div className="space-y-6" dir="ltr">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-neutral-800">Gestion des Paies</h1>
        <div className="flex items-center gap-3">
          <select
            value={mois}
            onChange={(e) => setMois(Number(e.target.value))}
            className="px-3 py-2 border border-neutral-300 rounded-xl text-sm bg-white"
          >
            {MOIS.map((m, i) => (
              <option key={i + 1} value={i + 1}>{m}</option>
            ))}
          </select>
          <input
            type="number"
            value={annee}
            onChange={(e) => setAnnee(Number(e.target.value))}
            className="px-3 py-2 border border-neutral-300 rounded-xl text-sm bg-white w-24"
            min={2024}
          />
          <button
            onClick={fetchPaies}
            className="px-4 py-2 bg-white border border-neutral-300 rounded-xl text-sm font-medium hover:bg-neutral-50"
          >
            Actualiser
          </button>
          <button
            onClick={handleCalculer}
            disabled={calculLoading}
            className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
          >
            {calculLoading ? 'Calcul...' : 'Calculer le mois'}
          </button>
        </div>
      </div>

      <div className="grid grid-cols-4 gap-4">
        {[
          { label: 'Masse salariale brute', value: totaux.brut, color: 'text-primary-700' },
          { label: 'Total IRG', value: totaux.irg, color: 'text-red-600' },
          { label: 'Total CNAS', value: totaux.cnas, color: 'text-amber-600' },
          { label: 'Net total', value: totaux.net, color: 'text-green-600' },
        ].map((kpi) => (
          <div key={kpi.label} className="bg-white rounded-2xl p-5 border border-neutral-100 shadow-sm">
            <p className="text-sm text-neutral-500 mb-1">{kpi.label}</p>
            <p className={`text-2xl font-bold ${kpi.color}`}>
              {kpi.value.toLocaleString('fr-DZ')} DA
            </p>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-2xl border border-neutral-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-neutral-100 bg-neutral-50">
                <th className="text-left px-4 py-3 font-semibold text-neutral-600">Enseignant</th>
                <th className="text-left px-4 py-3 font-semibold text-neutral-600">Contrat</th>
                <th className="text-right px-4 py-3 font-semibold text-neutral-600">Brut</th>
                <th className="text-right px-4 py-3 font-semibold text-neutral-600">CNAS</th>
                <th className="text-right px-4 py-3 font-semibold text-neutral-600">IRG</th>
                <th className="text-right px-4 py-3 font-semibold text-neutral-600">Net</th>
                <th className="text-center px-4 py-3 font-semibold text-neutral-600">Statut</th>
                <th className="text-center px-4 py-3 font-semibold text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={8} className="text-center py-8 text-neutral-400">Chargement...</td></tr>
              ) : paies.length === 0 ? (
                <tr><td colSpan={8} className="text-center py-8 text-neutral-400">
                  Aucune paie pour cette période. Cliquez sur "Calculer le mois".
                </td></tr>
              ) : paies.map((p) => (
                <tr key={p.id} className="border-b border-neutral-50 hover:bg-neutral-50">
                  <td className="px-4 py-3">
                    <button
                      onClick={() => setSelectedPaie(p)}
                      className="font-medium text-primary-700 hover:underline text-left"
                    >
                      {p.enseignant?.nom} {p.enseignant?.prenom}
                    </button>
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{p.enseignant?.type_contrat || '-'}</td>
                  <td className="px-4 py-3 text-right font-medium">
                    {Number(p.salaire_base || 0).toLocaleString('fr-DZ')} DA
                  </td>
                  <td className="px-4 py-3 text-right text-red-600">
                    {Number(p.cnas || 0).toLocaleString('fr-DZ')} DA
                  </td>
                  <td className="px-4 py-3 text-right text-amber-600">
                    {Number(p.irg || 0).toLocaleString('fr-DZ')} DA
                  </td>
                  <td className="px-4 py-3 text-right font-bold text-green-700">
                    {Number(p.salaire_net || 0).toLocaleString('fr-DZ')} DA
                  </td>
                  <td className="px-4 py-3 text-center">
                    <StatutBadge statut={p.statut} />
                  </td>
                  <td className="px-4 py-3 text-center">
                    <div className="flex items-center justify-center gap-2">
                      {p.statut === 'calculée' && (
                        <button
                          onClick={() => handleValider(p.id)}
                          className="px-3 py-1.5 text-xs font-medium bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-100"
                        >
                          Valider
                        </button>
                      )}
                      {p.statut === 'validée' && (
                        <button
                          onClick={() => handlePayer(p.id)}
                          className="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100"
                        >
                          Payer
                        </button>
                      )}
                      <button
                        onClick={() => setSelectedPaie(p)}
                        className="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-700 rounded-lg hover:bg-primary-100"
                      >
                        Détail
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {selectedPaie && (
        <PaieDetailDrawer
          paie={selectedPaie}
          onClose={() => setSelectedPaie(null)}
          onRefresh={fetchPaies}
        />
      )}
    </div>
  );
}
