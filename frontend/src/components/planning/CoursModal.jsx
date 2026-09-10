import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

import {
  AlertTriangle,
  BookOpen,
  Calendar,
  CalendarDays,
  CheckCircle,
  Clock,
  Hourglass,
  Pencil,
  Plus,
  Presentation,
  Repeat,
  Save,
  School,
  Wallet,
  X,
} from 'lucide-react';
import { useI18n } from '@context/I18nContext';

const schema = yup.object({
  enseignant_id: yup.string().required('validation_enseignant_requis'),
  groupe_id:     yup.string().required('validation_groupe_requis'),
  salle_id:      yup.string().nullable(),
  jour_semaine:  yup.number().min(0).max(6).required('validation_jour_requis'),
  heure_debut:   yup.string().required('validation_heure_debut_requise'),
  heure_fin:     yup.string().required('validation_heure_fin_requise'),
  recurrence:    yup.string().required(),
  date_debut:    yup.string().required('validation_date_debut_requise'),
  tarif_seance:  yup.number().min(0).nullable(),
});

// label = clé i18n.
const JOURS_OPTIONS = [
  { value: 0, label: 'jour_dimanche' }, { value: 1, label: 'jour_lundi' },
  { value: 2, label: 'jour_mardi' },    { value: 3, label: 'jour_mercredi' },
  { value: 4, label: 'jour_jeudi' },    { value: 5, label: 'jour_vendredi' },
  { value: 6, label: 'jour_samedi' },
];

export default function CoursModal({ isOpen, onClose, initialData, cours, onSuccess }) {
  const { t } = useI18n();
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
      const ok = window.confirm(t('cours_conflits_confirmer'));
      if (!ok) return;
      data.forcer = true;
    }
    setIsLoading(true);
    try {
      if (cours?.cours_id) {
        await api.put(`/cours/${cours.cours_id}`, data);
        toast.success(t('cours_modifie'));
      } else {
        await api.post('/cours', data);
        toast.success(t('cours_cree'));
      }
      onSuccess();
    } catch (err) {
      toast.error(err?.error?.message || t('error_operation'));
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
          <h2 className="text-xl font-bold text-neutral-800">{cours ? <><Pencil size={20} aria-hidden='true' />{t('cours_modifier_titre')}</> : <><Plus size={20} aria-hidden='true' />{t('cours_nouveau_titre')}</>}</h2>
          <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg transition-colors text-neutral-500"><X size={16} aria-label={t('close')} /></button>
        </div>
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          {conflits.length > 0 && (
            <div className="bg-orange-50 border border-orange-200 rounded-xl p-3">
              <p className="text-sm font-semibold text-orange-700 flex items-center gap-2"><AlertTriangle size={14} aria-hidden='true' /> {t('cours_conflits_detectes', { count: conflits.length })}</p>
              {conflits.map((c, i) => (
                <p key={i} className="text-xs text-orange-600 mt-1 ml-6">• {c.message}</p>
              ))}
            </div>
          )}
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Presentation size={14} aria-hidden='true' />{t('type_enseignant')} *</label>
            <select {...register('enseignant_id')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none transition-colors ${errors.enseignant_id ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`}>
              <option value="">{t('cours_select_enseignant')}</option>
              {enseignants.map(e => <option key={e.id} value={e.id}>{e.nom} {e.prenom}</option>)}
            </select>
            {errors.enseignant_id && <p className="text-xs text-danger-600 mt-1">{t(errors.enseignant_id.message)}</p>}
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><BookOpen size={14} aria-hidden='true' />{t('type_groupe')} *</label>
            <select {...register('groupe_id')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.groupe_id ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`}>
              <option value="">{t('cours_select_groupe')}</option>
              {groupes.map(g => <option key={g.id} value={g.id}>{g.nom} — {g.niveau_scolaire}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Calendar size={14} aria-hidden='true' />{t('cours_jour')} *</label>
              <select {...register('jour_semaine', { valueAsNumber: true })} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                {JOURS_OPTIONS.map(j => <option key={j.value} value={j.value}>{t(j.label)}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><School size={14} aria-hidden='true' />{t('type_salle')}</label>
              <select {...register('salle_id')} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                <option value="">{t('cours_aucune_salle')}</option>
                {salles.map(s => <option key={s.id} value={s.id}>{s.nom}</option>)}
              </select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Clock size={14} aria-hidden='true' />{t('cours_heure_debut')} *</label>
              <input type="time" {...register('heure_debut')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.heure_debut ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Clock size={14} aria-hidden='true' />{t('cours_heure_fin')} *</label>
              <input type="time" {...register('heure_fin')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.heure_fin ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Repeat size={14} aria-hidden='true' />{t('cours_recurrence')} *</label>
              <select {...register('recurrence')} className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500">
                <option value="hebdo">{t('recurrence_hebdo')}</option>
                <option value="bimensuel">{t('recurrence_bimensuel')}</option>
                <option value="mensuel">{t('recurrence_mensuel')}</option>
                <option value="unique">{t('recurrence_unique')}</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><CalendarDays size={14} aria-hidden='true' />{t('cours_date_debut')} *</label>
              <input type="date" {...register('date_debut')} className={`w-full px-3 py-2.5 rounded-xl border-2 text-sm outline-none ${errors.date_debut ? 'border-danger-400' : 'border-neutral-200 focus:border-primary-500'}`} />
            </div>
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5"><Wallet size={14} aria-hidden='true' />{t('cours_tarif_seance')}</label>
            <input type="number" {...register('tarif_seance')} placeholder="0" className="w-full px-3 py-2.5 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500" />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="flex-1 py-3 rounded-xl border-2 border-neutral-200 text-neutral-700 font-semibold text-sm hover:bg-neutral-50 transition-colors">{t('cancel')}</button>
            <button type="submit" disabled={isLoading || checkingConflits} className="flex-1 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
              {isLoading ? <><span className="animate-spin"><Hourglass size={16} aria-hidden='true' /></span> {t('sauvegarde_en_cours')}</> : <>{cours ? <><Save size={16} aria-hidden='true' />{t('edit')}</> : <><CheckCircle size={16} aria-hidden='true' />{t('cours_creer')}</>}</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
