import React, { useState, useEffect } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

export default function FactureModal({ isOpen, onClose, onSuccess }) {
  const [eleves, setEleves] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const { register, handleSubmit, control, reset, formState: { errors } } = useForm({
    defaultValues: {
      eleve_id: '',
      date_echeance: '',
      remise_pct: 0,
      lignes: [{ description: '', prix_unitaire: 0, quantite: 1, total: 0, type_ligne: 'cours' }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'lignes' });

  useEffect(() => {
    if (!isOpen) return;
    const load = async () => {
      try {
        const res = await api.get('/eleves', { params: { statut: 'actif', per_page: 200 } });
        setEleves(res.data || []);
      } catch { /* silent */ }
    };
    load();
  }, [isOpen]);

  const onSubmit = async (data) => {
    setIsLoading(true);
    try {
      await api.post('/factures', data);
      toast.success('Facture créée avec succès !');
      reset();
      onSuccess();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur de création');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-2xl max-h-[90vh] overflow-y-auto animate-slide-up">
        <div className="flex items-center justify-between p-6 border-b border-neutral-100">
          <h2 className="text-xl font-bold text-neutral-800">➕ Nouvelle facture</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg transition-colors text-neutral-500">✕</button>
        </div>
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">👨‍🎓 Élève *</label>
            <select {...register('eleve_id', { required: 'Élève requis' })} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.eleve_id ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`}>
              <option value="">Sélectionner un élève</option>
              {eleves.map(e => <option key={e.id} value={e.id}>{e.nom} {e.prenom} — {e.numero_inscription}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📅 Date échéance *</label>
              <input type="date" {...register('date_echeance', { required: true })} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500" />
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">🏷️ Remise (%)</label>
              <input type="number" {...register('remise_pct')} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500" />
            </div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-sm font-semibold text-neutral-700">📋 Lignes de facture</label>
              <button type="button" onClick={() => append({ description: '', prix_unitaire: 0, quantite: 1, total: 0, type_ligne: 'cours' })} className="text-xs px-3 py-1.5 bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition-colors">+ Ajouter une ligne</button>
            </div>
            <div className="space-y-2">
              {fields.map((field, index) => (
                <div key={field.id} className="flex gap-2 items-start bg-neutral-50 p-3 rounded-xl border border-neutral-100">
                  <div className="flex-1">
                    <input {...register(`lignes.${index}.description`, { required: true })} placeholder="Description" className="w-full px-3 py-2 rounded-lg border border-neutral-200 text-sm outline-none focus:border-primary-500" />
                  </div>
                  <div className="w-20">
                    <input type="number" {...register(`lignes.${index}.prix_unitaire`, { required: true, min: 0 })} placeholder="Prix" className="w-full px-3 py-2 rounded-lg border border-neutral-200 text-sm outline-none focus:border-primary-500" />
                  </div>
                  <div className="w-16">
                    <input type="number" {...register(`lignes.${index}.quantite`)} defaultValue={1} className="w-full px-3 py-2 rounded-lg border border-neutral-200 text-sm outline-none focus:border-primary-500" />
                  </div>
                  <input type="hidden" {...register(`lignes.${index}.type_ligne`)} value="cours" />
                  {fields.length > 1 && (
                    <button type="button" onClick={() => remove(index)} className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">🗑️</button>
                  )}
                </div>
              ))}
            </div>
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="flex-1 py-3 rounded-xl border-2 border-neutral-200 text-neutral-700 font-semibold text-sm hover:bg-neutral-50">Annuler</button>
            <button type="submit" disabled={isLoading} className="flex-1 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 disabled:opacity-60 flex items-center justify-center gap-2">
              {isLoading ? <><span className="animate-spin">⏳</span> Création...</> : '✅ Créer la facture'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
