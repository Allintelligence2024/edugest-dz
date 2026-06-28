import React, { useState, useEffect } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';

const schema = yup.object({
  nom: yup.string().min(2).required('Nom requis'),
  prenom: yup.string().min(2).required('Prénom requis'),
  sexe: yup.string().required('Sexe requis'),
  date_naissance: yup.string().required('Date de naissance requise'),
  niveau_scolaire: yup.string().required('Niveau requis'),
});

const NIVEAUX = [
  { group: 'Primaire', options: ['1AP','2AP','3AP','4AP','5AP'] },
  { group: 'Moyen', options: ['1AM','2AM','3AM','4AM'] },
  { group: 'Lycée', options: ['1AS','2AS','3AS'] },
  { group: 'Autre', options: ['universitaire','autre'] },
];

const STEPS = [
  { id: 'eleve', label: '👤 Élève', icon: '1' },
  { id: 'parents', label: '👨‍👩‍👦 Parents', icon: '2' },
  { id: 'recap', label: '✅ Récap', icon: '3' },
];

export default function EleveModal({ isOpen, eleve, onClose, onSuccess }) {
  const [step, setStep] = useState(0);
  const [wilayas, setWilayas] = useState([]);
  const [communes, setCommunes] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [photoPreview, setPhotoPreview] = useState(null);
  const isEdit = !!eleve;

  const { register, handleSubmit, watch, reset, setValue, formState: { errors } } = useForm({
    resolver: yupResolver(schema),
    defaultValues: { parents: [{ lien: 'père' }] },
  });

  const { fields: parentFields, append: addParent, remove: removeParent } = useFieldArray({ name: 'parents' });

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
        toast.success('Élève mis à jour !');
      } else {
        const res = await api.post('/eleves', rest);
        toast.success(`Élève ${res.data?.nom} créé ! 🎉`);
      }
      onSuccess();
    } catch (err) {
      const details = err?.error?.details;
      if (details) Object.values(details).flat().slice(0, 3).forEach(m => toast.error(m));
      else toast.error(err?.error?.message || 'Erreur');
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
              {isEdit ? `✏️ Modifier — ${eleve.nom} ${eleve.prenom}` : '➕ Nouvel élève'}
            </h2>
            <button onClick={onClose} className="p-2 hover:bg-neutral-100 rounded-lg text-neutral-400">✕</button>
          </div>
          <div className="flex items-center">
            {STEPS.map((s, i) => (
              <React.Fragment key={s.id}>
                <button type="button" onClick={() => i < step + 1 && setStep(i)}
                        className={`flex items-center gap-2 text-sm font-medium transition-colors ${i === step ? 'text-primary-700' : i < step ? 'text-green-600 cursor-pointer' : 'text-neutral-300 cursor-default'}`}>
                  <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all
                    ${i === step ? 'border-primary-600 bg-primary-600 text-white' : i < step ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-200 text-neutral-400'}`}>
                    {i < step ? '✓' : s.icon}
                  </span>
                  <span className="hidden sm:block">{s.label}</span>
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
                    {photoPreview ? <img src={photoPreview} className="w-full h-full object-cover" alt="" /> : <span className="text-3xl">👤</span>}
                  </div>
                  <label className="absolute -bottom-1 -right-1 w-7 h-7 bg-primary-600 text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-primary-700 transition-colors text-xs">
                    📷<input type="file" accept="image/*" onChange={handlePhotoChange} className="sr-only" />
                  </label>
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-neutral-600">Photo de l'élève (optionnel)</p>
                  <p className="text-xs text-neutral-400 mt-0.5">JPG, PNG • Max 2 Mo</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">Nom *</label>
                  <input {...register('nom')} className={`input ${errors.nom ? 'input-error' : ''}`} placeholder="BENALI"
                         onChange={e => setValue('nom', e.target.value.toUpperCase())} />
                  {errors.nom && <p className="error-msg">{errors.nom.message}</p>}
                </div>
                <div>
                  <label className="label">Prénom *</label>
                  <input {...register('prenom')} className={`input ${errors.prenom ? 'input-error' : ''}`} placeholder="Ahmed" />
                  {errors.prenom && <p className="error-msg">{errors.prenom.message}</p>}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">Nom arabe</label>
                  <input {...register('nom_ar')} className="input" dir="rtl" placeholder="بن علي" />
                </div>
                <div>
                  <label className="label">Prénom arabe</label>
                  <input {...register('prenom_ar')} className="input" dir="rtl" placeholder="أحمد" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="label">Sexe *</label>
                  <div className="flex gap-2">
                    {[{ v:'M', l:'👦 M' },{ v:'F', l:'👧 F' }].map(s => (
                      <label key={s.v} className={`flex-1 flex items-center justify-center py-2.5 rounded-xl border-2 cursor-pointer transition-all text-sm font-semibold
                        ${watch('sexe') === s.v ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-neutral-200 text-neutral-500 hover:border-neutral-300'}`}>
                        <input type="radio" {...register('sexe')} value={s.v} className="sr-only" />{s.l}
                      </label>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="label">Date naissance *</label>
                  <input type="date" {...register('date_naissance')} className={`input ${errors.date_naissance ? 'input-error' : ''}`} max={new Date().toISOString().split('T')[0]} />
                  {errors.date_naissance && <p className="error-msg">{errors.date_naissance.message}</p>}
                </div>
                <div>
                  <label className="label">Niveau *</label>
                  <select {...register('niveau_scolaire')} className={`input ${errors.niveau_scolaire ? 'input-error' : ''}`}>
                    <option value="">—</option>
                    {NIVEAUX.map(g => (
                      <optgroup key={g.group} label={g.group}>{g.options.map(n => <option key={n} value={n}>{n}</option>)}</optgroup>
                    ))}
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">Wilaya</label>
                  <select {...register('wilaya_id')} className="input">
                    <option value="">Sélectionner</option>
                    {wilayas.map(w => <option key={w.id} value={w.id}>{w.code} — {w.nom_fr}</option>)}
                  </select>
                </div>
                <div>
                  <label className="label">Commune</label>
                  <select {...register('commune_id')} className="input" disabled={!watchedWilaya}>
                    <option value="">Sélectionner</option>
                    {communes.map(c => <option key={c.id} value={c.id}>{c.nom_fr}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">École d'origine</label>
                <input {...register('ecole_origine')} className="input" placeholder="Ex: Lycée Colonel Amirouche" />
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
                <p className="text-sm font-semibold text-neutral-700">Contacts parents/tuteurs ({parentFields.length})</p>
                {parentFields.length < 3 && (
                  <button type="button" onClick={() => addParent({ lien: 'mère' })}
                          className="text-sm text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">➕ Ajouter un contact</button>
                )}
              </div>
              {parentFields.map((field, i) => (
                <div key={field.id} className="bg-neutral-50 rounded-xl p-4 space-y-3 relative">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Contact {i + 1} {i === 0 ? '(Principal)' : ''}</span>
                    {i > 0 && <button type="button" onClick={() => removeParent(i)} className="text-red-400 hover:text-red-600 text-xs">🗑️ Supprimer</button>}
                  </div>
                  <div className="grid grid-cols-3 gap-3">
                    <div>
                      <label className="label">Lien *</label>
                      <select {...register(`parents.${i}.lien`)} className="input">
                        {['père','mère','tuteur','frère','sœur','autre'].map(l => <option key={l} value={l}>{l}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="label">Nom *</label>
                      <input {...register(`parents.${i}.nom`)} className="input" placeholder="NOM" />
                    </div>
                    <div>
                      <label className="label">Prénom *</label>
                      <input {...register(`parents.${i}.prenom`)} className="input" placeholder="Prénom" />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">Téléphone 1 *</label>
                      <input {...register(`parents.${i}.telephone_1`)} className="input" placeholder="0555 XX XX XX" />
                    </div>
                    <div>
                      <label className="label">Téléphone 2</label>
                      <input {...register(`parents.${i}.telephone_2`)} className="input" placeholder="0555 XX XX XX" />
                    </div>
                  </div>
                  <div>
                    <label className="label">Email</label>
                    <input type="email" {...register(`parents.${i}.email`)} className="input" placeholder="parent@email.com" />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="label">Profession</label>
                      <input {...register(`parents.${i}.profession`)} className="input" placeholder="Médecin, Ingénieur..." />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              <div className="bg-green-50 border border-green-200 rounded-xl p-4">
                <h3 className="font-bold text-green-800 mb-3 flex items-center gap-2">✅ Récapitulatif</h3>
                <div className="flex items-center gap-3 mb-4">
                  {photoPreview ? <img src={photoPreview} className="w-16 h-16 rounded-xl object-cover" alt="" /> : <div className="w-16 h-16 rounded-xl bg-primary-100 flex items-center justify-center text-2xl">👤</div>}
                  <div>
                    <div className="text-lg font-bold text-neutral-800">{watch('nom')} {watch('prenom')}</div>
                    <div className="text-sm text-neutral-500">{watch('niveau_scolaire')} • {watch('sexe') === 'M' ? '👦' : '👧'}</div>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-sm">
                  {[['Date naissance', watch('date_naissance')], ['École origine', watch('ecole_origine') || '—'], ['Parents', `${parentFields.length} contact(s)`]].map(([k, v]) => (
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
            <button type="button" onClick={() => setStep(s => s - 1)} className="btn btn-secondary flex-1">← Retour</button>
          ) : (
            <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Annuler</button>
          )}
          {step < STEPS.length - 1 ? (
            <button type="button" onClick={() => setStep(s => s + 1)} className="btn btn-primary flex-1">Suivant →</button>
          ) : (
            <button type="button" onClick={handleSubmit(onSubmit)} disabled={isLoading}
                    className="btn btn-primary flex-1">
              {isLoading ? <><span className="animate-spin">⏳</span> Création...</> : isEdit ? '💾 Enregistrer' : '✅ Créer l\'élève'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
