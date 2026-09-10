import React, { useState } from 'react';
import { paiementApi } from '@api/facture.api';
import toast from 'react-hot-toast';

const MODES_PAIEMENT = [
  { value: 'especes', label: 'Espèces', icon: '💵' },
  { value: 'cib', label: 'CIB', icon: '💳' },
  { value: 'dahabia', label: 'Dahabia', icon: '💳' },
  { value: 'baridimob', label: 'BaridiMob', icon: '📱' },
  { value: 'virement', label: 'Virement', icon: '🏦' },
  { value: 'cheque', label: 'Chèque', icon: '📄' },
];

export default function PaiementModal({ facture, onClose, onSuccess }) {
  const [mode, setMode] = useState('');
  const [montant, setMontant] = useState(facture?.total_ttc || 0);
  const [loading, setLoading] = useState(false);

  const isEnLigne = ['cib', 'dahabia', 'baridimob'].includes(mode);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!mode) return toast.error('Sélectionnez un mode de paiement');

    setLoading(true);
    try {
      if (isEnLigne) {
        const res = await paiementApi.create({
          facture_id: facture.id,
          mode_paiement: mode,
          montant,
          statut: mode === 'baridimob' ? 'en_attente' : 'confirme',
        });

        toast.success('Paiement initié');

        if (res.data?.redirect_url) {
          window.open(res.data.redirect_url, '_blank');
        }
      } else {
        await paiementApi.create({
          facture_id: facture.id,
          mode_paiement: mode,
          montant,
          statut: 'confirme',
        });
        toast.success('Paiement enregistré');
      }

      onSuccess?.();
      onClose();
    } catch (e) {
      toast.error(e?.error?.message || 'Erreur paiement');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="fixed inset-0 bg-black/20 z-50" onClick={onClose} />
      <div className="fixed inset-0 flex items-center justify-center z-50 p-4">
        <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onClick={(e) => e.stopPropagation()}>
          <div className="flex items-center justify-between mb-5">
            <h2 className="text-lg font-bold text-neutral-800">Enregistrer un paiement</h2>
            <button onClick={onClose} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-neutral-100 text-neutral-400">&times;</button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <p className="text-sm text-neutral-500 mb-1">Facture</p>
              <p className="text-sm font-medium text-neutral-700">{facture?.numero_facture || '-'}</p>
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-600 mb-2">Montant (DZD)</label>
              <input
                type="number"
                value={montant}
                onChange={(e) => setMontant(Number(e.target.value))}
                className="w-full px-4 py-2.5 border border-neutral-300 rounded-xl text-sm"
                min={100}
                step={100}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-600 mb-2">Mode de paiement</label>
              <div className="grid grid-cols-3 gap-2">
                {MODES_PAIEMENT.map((m) => (
                  <button
                    key={m.value}
                    type="button"
                    onClick={() => setMode(m.value)}
                    className={`flex flex-col items-center gap-1 p-3 rounded-xl border text-sm transition-colors ${
                      mode === m.value
                        ? 'border-primary-500 bg-primary-50 text-primary-700'
                        : 'border-neutral-200 text-neutral-600 hover:border-neutral-300'
                    }`}
                  >
                    <span className="text-xl">{m.icon}</span>
                    <span>{m.label}</span>
                  </button>
                ))}
              </div>
            </div>

            {isEnLigne && (
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-700">
                {mode === 'baridimob'
                  ? 'Une référence de virement sera générée. Confirmez manuellement après réception.'
                  : 'Vous serez redirigé vers la plateforme de paiement sécurisée Satim.'}
              </div>
            )}

            <div className="flex items-center gap-3 pt-2">
              <button
                type="button"
                onClick={onClose}
                className="flex-1 py-2.5 border border-neutral-300 rounded-xl text-sm font-medium text-neutral-600 hover:bg-neutral-50"
              >
                Annuler
              </button>
              <button
                type="submit"
                disabled={loading || !mode}
                className="flex-1 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
              >
                {loading ? 'Traitement...' : 'Confirmer le paiement'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
