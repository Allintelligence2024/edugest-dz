import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

const schema = yup.object({
  enseignant_id: yup.string().required('Enseignant requis'),
  groupe_id:     yup.string().required('Groupe requis'),
  salle_id:      yup.string().nullable(),
  jour_semaine:  yup.number().min(0).max(6).required('Jour requis'),
  heure_debut:   yup.string().required('Heure de début requise'),
  heure_fin:     yup.string().required('Heure de fin requise'),
  recurrence:    yup.string().required(),
  date_debut:    yup.string().required('Date de début requise'),
  tarif_seance:  yup.number().min(0).nullable(),
});

const JOURS_OPTIONS = [
  { value: 0, label: 'Dimanche' }, { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },    { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },    { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
];

export default function CoursModal({ isOpen, onClose, initialData, cours, onSuccess }) {
  const [enseignants, setEnseignants] = useState([]);
  const [groupes, setGroupes] = useState([]);
  const [salles, setSalles] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [conflits, setConflits] = useState([]);
  const [checkingConflits, setCheckingConflits] = useState(false);

  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      recurrence: 'hebdo',
      jour_semaine: initialData?.jour_semaine ?? 1,
      heure_debut: initialData?.heure_debut ?? '09:00',
      heure_fin: '11:00',
      tarif_seance: 0,
    },
  });

  const watchedEnseignant = watch('enseignant_id');
  const watchedJour = watch('jour_semaine');
  const watchedDebut = watch('heure_debut');
  const watchedFin = watch('heure_fin');
  const watchedSalle = watch('salle_id');

  useEffect(() => {
    if (!isOpen) return;
    const load = async () => {
      try {
        const [ens, grp, sal] = await Promise.all([
          api.get('/enseignants', { params: { statut: 'actif', per_page: 100 } }),
          api.get('/groupes', { params: { statut: 'actif', per_page: 100 } }),
          api.get('/salles', { params: { per_page: 100 } }),
        ]);
        setEnseignants(ens.data || []);
        setGroupes(grp.data || []);
        setSalles(sal.data || []);
      } catch { /* silent */ }
    };
    load();
  }, [isOpen]);

  useEffect(() => {
    if (cours) {
      reset({
        enseignant_id: cours.enseignant?.id,
        groupe_id: cours.groupe?.id,
        salle_id: cours.salle?.id,
        jour_semaine: cours.jour_num,
        heure_debut: cours.heure_debut,
        heure_fin: cours.heure_fin,
        recurrence: cours.recurrence,
        date_debut: cours.date_debut,
        date_fin: cours.date_fin,
        tarif_seance: cours.tarif_seance,
      });
    }
  }, [cours, reset]);

  useEffect(() => {
    if (!watchedEnseignant || !watchedJour || !watchedDebut || !watchedFin) return;
    const timer = setTimeout(async () => {
      setCheckingConflits(true);
      try {
        const res = await api.get('/planning/conflits', {
          params: {
            enseignant_id: watchedEnseignant,
            jour_semaine: watchedJour,
            heure_debut: watchedDebut,
            heure_fin: watchedFin,
            salle_id: watchedSalle,
            exclude_id: cours?.id,
          }
        });
        setConflits(res.conflits || []);
      } catch { setConflits([]); }
      finally { setCheckingConflits(false); }
    }, 500);
    return () => clearTimeout(timer);
  }, [watchedEnseignant, watchedJour, watchedDebut, watchedFin, watchedSalle]);

  const onSubmit = async (data) => {
    if (conflits.length > 0) {
      const ok = window.confirm('⚠️ Des conflits ont été détectés. Voulez-vous forcer la création ?');
      if (!ok) return;
      data.forcer = true;
    }
    setIsLoading(true);
    try {
      if (cours?.cours_id) {
        await api.put(`/cours/${cours.cours_id}`, data);
        toast.success('Cours modifié avec succès !');
      } else {
        await api.post('/cours', data);
        toast.success('Cours créé et séances planifiées !');
      }
      onSuccess();
    } catch (err) {
      toast.error(err?.error?.message || 'Erreur lors de la sauvegarde');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-lg max-h-[90vh] overflow-y-auto animate-slide-up">
        <div className="flex items-center justify-between p-6 border-b border-neutral-100">
          <h2 className="text-xl font-bold text-neutral-800">{cours ? '✏️ Modifier le cours' : '➕ Nouveau cours'}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg transition-colors text-neutral-500">✕</button>
        </div>
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          {conflits.length > 0 && (
            <div className="bg-orange-50 border border-orange-200 rounded-xl p-3">
              <p className="text-sm font-semibold text-orange-700 flex items-center gap-2">⚠️ {conflits.length} conflit(s) détecté(s)</p>
              {conflits.map((c, i) => (
                <p key={i} className="text-xs text-orange-600 mt-1 ml-6">• {c.message}</p>
              ))}
            </div>
          )}
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">👨‍🏫 Enseignant *</label>
            <select {...register('enseignant_id')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none transition-colors ${errors.enseignant_id ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`}>
              <option value="">Sélectionner un enseignant</option>
              {enseignants.map(e => <option key={e.id} value={e.id}>{e.nom} {e.prenom}</option>)}
            </select>
            {errors.enseignant_id && <p className="text-xs text-danger-600 mt-1">{errors.enseignant_id.message}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📚 Groupe *</label>
            <select {...register('groupe_id')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.groupe_id ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`}>
              <option value="">Sélectionner un groupe</option>
              {groupes.map(g => <option key={g.id} value={g.id}>{g.nom} — {g.niveau_scolaire}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📅 Jour *</label>
              <select {...register('jour_semaine', { valueAsNumber: true })} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                {JOURS_OPTIONS.map(j => <option key={j.value} value={j.value}>{j.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">🏫 Salle</label>
              <select {...register('salle_id')} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                <option value="">Aucune salle</option>
                {salles.map(s => <option key={s.id} value={s.id}>{s.nom}</option>)}
              </select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">🕐 Heure début *</label>
              <input type="time" {...register('heure_debut')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.heure_debut ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">🕑 Heure fin *</label>
              <input type="time" {...register('heure_fin')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.heure_fin ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">🔁 Récurrence *</label>
              <select {...register('recurrence')} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                <option value="hebdo">Hebdomadaire</option>
                <option value="bimensuel">Bimensuel</option>
                <option value="mensuel">Mensuel</option>
                <option value="unique">Unique</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5">📆 Date début *</label>
              <input type="date" {...register('date_debut')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.date_debut ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">💰 Tarif par séance (DA)</label>
            <input type="number" {...register('tarif_seance')} placeholder="0" className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500" />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="flex-1 py-3 rounded-xl border-2 border-neutral-200 text-neutral-700 font-semibold text-sm hover:bg-neutral-50 transition-colors">Annuler</button>
            <button type="submit" disabled={isLoading || checkingConflits} className="flex-1 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
              {isLoading ? <><span className="animate-spin">⏳</span> Sauvegarde...</> : <>{cours ? '💾 Modifier' : '✅ Créer le cours'}</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
