import React, { useState, useEffect } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

import { Camera, Check, CheckCircle, Hourglass, PartyPopper, Pencil, Plus, Save, Trash2, User, X } from 'lucide-react';
import { useI18n } from '@context/I18nContext';

const schema = yup.object({
  nom: yup.string().min(2).required('Nom requis'),
  prenom: yup.string().min(2).required('validation_prenom_requis'),
  sexe: yup.string().required('validation_sexe_requis'),
  date_naissance: yup.string().required('validation_date_naissance_requise'),
  niveau_scolaire: yup.string().required('validation_niveau_requis'),
});

// group = clé i18n traduite au rendu.
const NIVEAUX = [
  { group: 'groupe_niveau_primaire', options: ['1AP','2AP','3AP','4AP','5AP'] },
  { group: 'groupe_niveau_moyen', options: ['1AM','2AM','3AM','4AM'] },
  { group: 'groupe_niveau_lycee', options: ['1AS','2AS','3AS'] },
  { group: 'groupe_niveau_autre', options: ['universitaire','autre'] },
];

// Énums backend : comparaisons et valeurs POST inchangées ; l'affichage
// passe par une clé, avec repli sur la valeur brute.
const LIEN_PARENTAL = { 'père': 'lien_pere', 'mère': 'lien_mere', 'tuteur': 'lien_tuteur', 'frère': 'lien_frere', 'sœur': 'lien_soeur', 'autre': 'lien_autre' };

// label = clé i18n.
const STEPS = [
  { id: 'eleve', label: 'eleve_step_eleve', icon: '1' },
  { id: 'parents', label: 'eleve_step_parents', icon: '2' },
  { id: 'recap', label: 'eleve_step_recap', icon: '3' },
];

export default function EleveModal({ isOpen, eleve, onClose, onSuccess }) {
  const { t } = useI18n();
  const [step, setStep] = useState(0);
  const [wilayas, setWilayas] = useState([]);
  const [communes, setCommunes] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [photoPreview, setPhotoPreview] = useState(null);
  const isEdit = !!eleve;

  const { register, handleSubmit, watch, reset, setValue, control, formState: { errors } } = useForm({
    resolver: yupResolver(schema),
    defaultValues: { parents: [{ lien: 'père' }] },
  });

  const { fields: parentFields, append: addParent, remove: removeParent } = useFieldArray({ control, name: 'parents' });

  const watchedWilaya = watch('wilaya_id');

  useEffect(() => {
    if (!isOpen) return;
    setStep(0);
    api.get('/parametres/wilayas').then(r => setWilayas(r.data || []));
  }, [isOpen]);

  useEffect(() => {
    if (!watchedWilaya) return;
    api.get(`/parametres/communes/${watchedWilaya}`).then(r => setCommunes(r.data || []));
  }, [watchedWilaya]);

  useEffect(() => {
    if (eleve) {
      reset({
        ...eleve,
        date_naissance: eleve.date_naissance,
        parents: eleve.parents?.length ? eleve.parents.map(p => ({...p})) : [{ lien: 'père' }],
      });
      setPhotoPreview(eleve.photo_url);
    } else {
      reset({ parents: [{ lien: 'père' }] });
      setPhotoPreview(null);
    }
  }, [eleve, reset]);

  const handlePhotoChange = (e) => {
    const file = e.target.files?.[0];
    if (file) { setValue('photo_file', file); setPhotoPreview(URL.createObjectURL(file)); }
  };

  const onSubmit = async (data) => {
    setIsLoading(true);
    try {
      const { photo_file, ...rest } = data;
      if (isEdit) {
        const res = await api.put(`/eleves/${eleve.id}`, rest);
        if (photo_file) { const fd = new FormData(); fd.append('photo', photo_file); await api.post(`/eleves/${eleve.id}/photo`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }); }
        toast.success(t('eleve_mis_a_jour'));
      } else {
        const res = await api.post('/eleves', rest);
        toast.success(t('eleve_cree', { nom: res.data?.nom }), { icon: <PartyPopper size={18} aria-hidden="true" /> });
      }
      onSuccess();
    } catch (err) {
      const details = err?.error?.details;
      if (details) Object.values(details).flat().slice(0, 3).forEach(m => toast.error(m));
      else toast.error(err?.error?.message || t('error_operation'));
    } finally { setIsLoading(false); }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-modal w-full max-w-xl max-h-[95vh] flex flex-col animate-slide-up">
        {/* Header */}
        <div className="p-5 border-b border-neutral-100 flex-shrink-0">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold text-neutral-800">
              {isEdit ? <><Pencil size={18} aria-hidden='true' />{t('edit')} — {eleve.nom} {eleve.prenom}</> : <><Plus size={18} aria-hidden='true' />{t('eleve_nouveau')}</>}
            </h2>
            <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg text-neutral-400"><X size={16} aria-label={t('close')} /></button>
          </div>
          <div className="flex items-center">
            {STEPS.map((s, i) => (
              <React.Fragment key={s.id}>
                <button type="button" onClick={() => i < step + 1 && setStep(i)}
                        className={`flex items-center gap-2 text-sm font-medium transition-colors ${i === step ? 'text-primary-700' : i < step ? 'text-green-600 cursor-pointer' : 'text-neutral-300 cursor-default'}`}>
                  <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all
                    ${i === step ? 'border-primary-600 bg-primary-600 text-white' : i < step ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-200 text-neutral-400'}`}>
                    {i < step ? <Check size={16} aria-hidden='true' /> : s.icon}
                  </span>
                  <span className="hidden sm:block">{t(s.label)}</span>
                </button>
                {i < STEPS.length - 1 && <div className={`flex-1 h-0.5 mx-2 transition-colors ${i < step ? 'bg-green-400' : 'bg-neutral-200'}`} />}
              </React.Fragment>
            ))}
          </div>
        </div>

        {/* Body */}
        <form className="flex-1 overflow-y-auto p-5 space-y-4">
          {step === 0 && (
            <>
              <div className="flex items-center gap-4">
                <div className="relative w-20 h-20 flex-shrink-0">
                  <div className="w-20 h-20 rounded-2xl bg-neutral-100 overflow-hidden border-2 border-dashed border-neutral-300 flex items-center justify-center">
                    {photoPreview ? <img src={photoPreview} className="w-full h-full object-cover" alt="" /> : <span className="text-3xl"><User size={30} aria-hidden='true' /></span>}
                  </div>
                  <label className="absolute -bottom-1 -right-1 w-7 h-7 bg-primary-600 text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-primary-700 transition-colors text-xs">
                    <Camera size={12} aria-hidden='true' /><input type="file" accept="image/*" onChange={handlePhotoChange} className="sr-only" />
                  </label>
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-neutral-600">{t('eleve_photo_optionnel')}</p>
                  <p className="text-xs text-neutral-400 mt-0.5">JPG, PNG • Max 2 Mo</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">{t('name')} *</label>
                  <input {...register('nom')} className={`input ${errors.nom ? 'input-error' : ''}`} placeholder="BENALI"
                         onChange={e => setValue('nom', e.target.value.toUpperCase())} />
                  {errors.nom && <p className="error-msg">{t(errors.nom.message)}</p>}
                </div>
                <div>
                  <label className="label">{t('eleve_prenom')} *</label>
                  <input {...register('prenom')} className={`input ${errors.prenom ? 'input-error' : ''}`} placeholder="Ahmed" />
                  {errors.prenom && <p className="error-msg">{t(errors.prenom.message)}</p>}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">{t('eleve_nom_arabe')}</label>
                  <input {...register('nom_ar')} className="input" dir="rtl" placeholder="بن علي" />
                </div>
                <div>
                  <label className="label">{t('eleve_prenom_arabe')}</label>
                  <input {...register('prenom_ar')} className="input" dir="rtl" placeholder="أحمد" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="label">{t('eleve_sexe')} *</label>
                  <div className="flex gap-2">
                    {[{ v:'M', l: <><User size={14} aria-hidden="true" /> M</> },{ v:'F', l: <><User size={14} aria-hidden="true" /> F</> }].map(s => (
                      <label key={s.v} className={`flex-1 flex items-center justify-center py-2.5 rounded-xl border-2 cursor-pointer transition-all text-sm font-semibold
                        ${watch('sexe') === s.v ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-neutral-200 text-neutral-500 hover:border-neutral-300'}`}>
                        <input type="radio" {...register('sexe')} value={s.v} className="sr-only" />{s.l}
                      </label>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="label">{t('eleve_date_naissance')} *</label>
                  <input type="date" {...register('date_naissance')} className={`input ${errors.date_naissance ? 'input-error' : ''}`} max={new Date().toISOString().split('T')[0]} />
                  {errors.date_naissance && <p className="error-msg">{t(errors.date_naissance.message)}</p>}
                </div>
                <div>
                  <label className="label">{t('students_level')} *</label>
                  <select {...register('niveau_scolaire')} className={`input ${errors.niveau_scolaire ? 'input-error' : ''}`}>
                    <option value="">—</option>
                    {NIVEAUX.map(g => (
                      <optgroup key={g.group} label={t(g.group)}>{g.options.map(n => <option key={n} value={n}>{n}</option>)}</optgroup>
                    ))}
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">{t('eleve_wilaya')}</label>
                  <select {...register('wilaya_id')} className="input">
                    <option value="">{t('select')}</option>
                    {wilayas.map(w => <option key={w.id} value={w.id}>{w.code} — {w.nom_fr}</option>)}
                  </select>
                </div>
                <div>
                  <label className="label">{t('eleve_commune')}</label>
                  <select {...register('commune_id')} className="input" disabled={!watchedWilaya}>
                    <option value="">{t('select')}</option>
                    {communes.map(c => <option key={c.id} value={c.id}>{c.nom_fr}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">{t('eleve_ecole_origine')}</label>
                <input {...register('ecole_origine')} className="input" placeholder={t('eleve_ecole_exemple')} />
              </div>
              <div>
                <label className="label">Notes internes</label>
                <textarea {...register('notes_internes')} rows={2} className="input resize-none" placeholder="Remarques internes..." />
              </div>
            </>
          )}

          {step === 1 && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-neutral-700">{t('eleve_contacts_tuteurs', { count: parentFields.length })}</p>
                {parentFields.length < 3 && (
                  <button type="button" onClick={() => addParent({ lien: 'mère' })}
                          className="text-sm text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1"><Plus size={14} aria-hidden='true' />{t('eleve_ajouter_contact')}</button>
                )}
              </div>
              {parentFields.map((field, i) => (
                <div key={field.id} className="bg-neutral-50 rounded-xl p-4 space-y-3 relative">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">{t('eleve_contact_numero', { numero: i + 1 })} {i === 0 ? t('eleve_contact_principal') : ''}</span>
                    {i > 0 && <button type="button" onClick={() => removeParent(i)} className="text-red-400 hover:text-red-600 text-xs"><Trash2 size={12} aria-hidden='true' />{t('delete')}</button>}
                  </div>
                  <div className="grid grid-cols-3 gap-3">
                    <div>
                      <label className="label">{t('eleve_lien')} *</label>
                      <select {...register(`parents.${i}.lien`)} className="input">
                        {['père','mère','tuteur','frère','sœur','autre'].map(l => <option key={l} value={l}>{LIEN_PARENTAL[l] ? t(LIEN_PARENTAL[l]) : l}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="label">{t('name')} *</label>
                      <input {...register(`parents.${i}.nom`)} className="input" placeholder="NOM" />
                    </div>
                    <div>
                      <label className="label">{t('eleve_prenom')} *</label>
                      <input {...register(`parents.${i}.prenom`)} className="input" placeholder={t('eleve_prenom')} />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">{t('eleve_telephone_1')} *</label>
                      <input {...register(`parents.${i}.telephone_1`)} className="input" placeholder="0555 XX XX XX" />
                    </div>
                    <div>
                      <label className="label">{t('eleve_telephone_2')}</label>
                      <input {...register(`parents.${i}.telephone_2`)} className="input" placeholder="0555 XX XX XX" />
                    </div>
                  </div>
                  <div>
                    <label className="label">Email</label>
                    <input type="email" {...register(`parents.${i}.email`)} className="input" placeholder="parent@email.com" />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">{t('eleve_profession')}</label>
                      <input {...register(`parents.${i}.profession`)} className="input" placeholder={t('eleve_profession_exemple')} />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              <div className="bg-green-50 border border-green-200 rounded-xl p-4">
                <h3 className="font-bold text-green-800 mb-3 flex items-center gap-2"><CheckCircle size={16} aria-hidden='true' />{t('eleve_recapitulatif')}</h3>
                <div className="flex items-center gap-3 mb-4">
                  {photoPreview ? <img src={photoPreview} className="w-16 h-16 rounded-xl object-cover" alt="" /> : <div className="w-16 h-16 rounded-xl bg-primary-100 flex items-center justify-center text-2xl"><User size={24} aria-hidden='true' /></div>}
                  <div>
                    <div className="text-lg font-bold text-neutral-800">{watch('nom')} {watch('prenom')}</div>
                    <div className="text-sm text-neutral-500">{watch('niveau_scolaire')} • {watch('sexe')}</div>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-sm">
                  {[[t('eleve_date_naissance'), watch('date_naissance')], [t('eleve_ecole_origine'), watch('ecole_origine') || '—'], [t('eleve_step_parents'), t('eleve_contacts_count', { count: parentFields.length })]].map(([k, v]) => (
                    <div key={k} className="bg-white rounded-lg p-2.5">
                      <div className="text-xs text-neutral-400">{k}</div>
                      <div className="font-medium text-neutral-800 mt-0.5">{v}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </form>

        {/* Footer */}
        <div className="p-4 border-t border-neutral-100 flex gap-3 flex-shrink-0">
          {step > 0 ? (
            <button type="button" onClick={() => setStep(s => s - 1)} className="btn btn-secondary flex-1">← {t('back')}</button>
          ) : (
            <button type="button" onClick={onClose} className="btn btn-secondary flex-1">{t('cancel')}</button>
          )}
          {step < STEPS.length - 1 ? (
            <button type="button" onClick={() => setStep(s => s + 1)} className="btn btn-primary flex-1">{t('next')} →</button>
          ) : (
            <button type="button" onClick={handleSubmit(onSubmit)} disabled={isLoading}
                    className="btn btn-primary flex-1">
              {isLoading ? <><span className="animate-spin"><Hourglass size={16} aria-hidden='true' /></span> {t('creation_en_cours')}</> : isEdit ? <><Save size={16} aria-hidden='true' />{t('save')}</> : <><CheckCircle size={16} aria-hidden='true' />{t('eleve_creer')}</>}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
