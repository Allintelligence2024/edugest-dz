import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

const MODES = [
  { value: 'espèces',   label: '💵 Espèces' },
  { value: 'cib',       label: '💳 CIB' },
  { value: 'dahabia',   label: '💳 Dahabia' },
  { value: 'baridimob', label: '📱 BaridiMob' },
  { value: 'virement',  label: '🏦 Virement Bancaire' },
  { value: 'chèque',    label: '📋 Chèque' },
];

export default function PaiementModal({ isOpen, facture, onClose, onSuccess }) {
  const [isLoading, setIsLoading] = useState(false);

  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm({
    defaultValues: {
      mode_paiement: 'espèces',
      date_paiement: new Date().toISOString().split('T')[0],
    }
  });

  const montantRestant = facture
    ? facture.total_ttc - (facture.paiements?.filter(p => p.statut === 'confirmé')
                                             .reduce((s, p) => s + Number(p.montant), 0) || 0)
    : 0;

  const onSubmit = async (data) => {
    setIsLoading(true);
    try {
      await api.post('/paiements', {
        ...data,
        facture_id: facture.id,
        montant: parseFloat(data.montant),
      });
      toast.success(`Paiement de ${Number(data.montant).toLocaleString()} DA enregistré ! ✅`);
      reset();
      onSuccess();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur lors de l\'enregistrement');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isOpen || !facture) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-md animate-slide-up">
        <div className="bg-gradient-to-r from-green-600 to-green-500 p-6 rounded-t-2xl">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-xl font-bold text-white">💳 Enregistrer un paiement</h2>
              <p className="text-green-100 text-sm mt-1">Facture {facture.numero_facture}</p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-white/20 rounded-lg transition-colors text-white">✕</button>
          </div>
          <div className="mt-4 bg-white/15 rounded-xl p-3 text-white">
            <div className="flex justify-between text-sm"><span>Total facture :</span><span className="font-bold">{Number(facture.total_ttc).toLocaleString()} DA</span></div>
            <div className="flex justify-between text-sm mt-1"><span>Montant restant :</span><span className="font-bold text-yellow-200">{Number(montantRestant).toLocaleString()} DA</span></div>
          </div>
        </div>
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">💰 Montant (DA) *</label>
            <input type="number" step="0.01" {...register('montant', { required: 'Le montant est requis', min: { value: 1, message: 'Montant minimum : 1 DA' }, max: { value: montantRestant, message: `Maximum : ${montantRestant} DA` } })} defaultValue={montantRestant} className={`w-full px-4 py-3 rounded-xl border-2 text-sm outline-none font-bold text-lg transition-colors ${errors.montant ? 'border-danger-400 bg-red-50' : 'border-neutral-200 focus:border-green-500'}`} />
            {errors.montant && <p className="text-xs text-danger-600 mt-1">{errors.montant.message}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-2">🏧 Mode de paiement *</label>
            <div className="grid grid-cols-2 gap-2">
              {MODES.map(mode => (
                <label key={mode.value} className={`flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all ${watch('mode_paiement') === mode.value ? 'border-green-500 bg-green-50' : 'border-neutral-200 hover:border-neutral-300'}`}>
                  <input type="radio" value={mode.value} {...register('mode_paiement')} className="sr-only" />
                  <span className="text-sm font-medium text-neutral-700">{mode.label}</span>
                </label>
              ))}
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📅 Date du paiement *</label>
            <input type="date" {...register('date_paiement', { required: 'Date requise' })} className="w-full px-4 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-green-500" />
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📝 Notes (optionnel)</label>
            <input type="text" {...register('notes')} placeholder="Référence chèque, transaction..." className="w-full px-4 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-green-500" />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="flex-1 py-3 rounded-xl border-2 border-neutral-200 text-neutral-700 font-semibold text-sm hover:bg-neutral-50">Annuler</button>
            <button type="submit" disabled={isLoading} className="flex-1 py-3 rounded-xl bg-green-600 text-white font-semibold text-sm hover:bg-green-700 transition-colors disabled:opacity-60 flex items-center justify-center gap-2">
              {isLoading ? <><span className="animate-spin">⏳</span> Traitement...</> : '✅ Confirmer le paiement'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
