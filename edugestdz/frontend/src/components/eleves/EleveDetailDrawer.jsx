import React, { useState, useEffect } from 'react';
import { toast } from 'react-hot-toast';
import api from '@api/axiosInstance';
import MatchingSuggestions from '@components/eleves/MatchingSuggestions';

const TABS = [
  { id: 'profil', label: '👤 Profil' },
  { id: 'notes', label: '📊 Notes' },
  { id: 'presences', label: '✅ Présences' },
  { id: 'paiements', label: '💰 Paiements' },
  { id: 'matching', label: '🤖 Suggestions IA' },
];

export default function EleveDetailDrawer({ isOpen, eleve, onClose, onEdit }) {
  const [activeTab, setActiveTab] = useState('profil');
  const [detail, setDetail] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!isOpen || !eleve) return;
    setActiveTab('profil');
    loadDetail();
  }, [isOpen, eleve?.id]);

  const loadDetail = async () => {
    if (!eleve) return;
    setIsLoading(true);
    try { const res = await api.get(`/eleves/${eleve.id}`); setDetail(res.data); }
    catch { toast.error('Erreur de chargement'); }
    finally { setIsLoading(false); }
  };

  if (!isOpen) return null;

  const e = detail ?? eleve;

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/30" onClick={onClose} />
      <div className={`fixed right-0 top-0 h-full z-50 bg-white shadow-2xl flex flex-col transition-all duration-300
                        ${isOpen ? 'translate-x-0' : 'translate-x-full'} w-full sm:w-[480px]`}>

        <div className="bg-gradient-to-r from-primary-700 to-primary-500 p-5 flex-shrink-0">
          <div className="flex items-start gap-4">
            <div className="w-16 h-16 rounded-xl bg-white/20 overflow-hidden flex-shrink-0 flex items-center justify-center text-2xl">
              {e?.photo_url ? <img src={e.photo_url} className="w-full h-full object-cover" alt="" /> : <span>👤</span>}
            </div>
            <div className="flex-1 min-w-0">
              <h2 className="text-xl font-bold text-white truncate">{e?.nom} {e?.prenom}</h2>
              <div className="flex items-center gap-2 mt-1 flex-wrap">
                <span className="text-primary-100 text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full">{e?.numero_inscription}</span>
                <span className="text-primary-100 text-xs bg-white/10 px-2 py-0.5 rounded-full">{e?.niveau_scolaire}</span>
              </div>
            </div>
            <div className="flex items-center gap-1 flex-shrink-0">
              <button onClick={onEdit} className="p-2 hover:bg-white/20 rounded-lg transition-colors text-white text-sm" title="Modifier">✏️</button>
              <button onClick={onClose} className="p-2 hover:bg-white/20 rounded-lg transition-colors text-white text-xl">✕</button>
            </div>
          </div>

          <div className="flex gap-1 mt-4 overflow-x-auto pb-1">
            {TABS.map(tab => (
              <button key={tab.id} onClick={() => setActiveTab(tab.id)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all flex-shrink-0
                        ${activeTab === tab.id ? 'bg-white text-primary-700' : 'text-primary-100 hover:bg-white/20'}`}>
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto p-5">
          {isLoading ? (
            <div className="flex justify-center py-12"><div className="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin" /></div>
          ) : activeTab === 'profil' && (
            <div className="space-y-5">
              <section>
                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-3">Informations personnelles</h3>
                <div className="grid grid-cols-2 gap-2">
                  {[['🎂 Date naissance', e?.date_naissance], ['📍 Wilaya', e?.wilaya?.nom_fr || '—'], ['🏫 École origine', e?.ecole_origine || '—'], ['🌍 Nationalité', e?.nationalite || 'Algérienne']].map(([k, v]) => (
                    <div key={k} className="bg-neutral-50 rounded-xl p-3">
                      <div className="text-xs text-neutral-400">{k}</div>
                      <div className="text-sm font-medium text-neutral-800 mt-0.5">{v}</div>
                    </div>
                  ))}
                </div>
              </section>
              <section>
                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-3">Groupes inscrits</h3>
                {e?.groupes?.length ? (
                  <div className="space-y-2">{e.groupes.map(g => (
                    <div key={g.id} className="flex items-center gap-3 p-3 bg-neutral-50 rounded-xl border border-neutral-100">
                      <div className="w-2 h-8 rounded-full" style={{ backgroundColor: g.couleur || '#1E5EBC' }} />
                      <div><div className="text-sm font-semibold">{g.matiere}</div><div className="text-xs text-neutral-400">{g.nom}</div></div>
                    </div>
                  ))}</div>
                ) : <p className="text-sm text-neutral-400 italic">Aucun groupe inscrit</p>}
              </section>
              <section>
                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-3">Contacts</h3>
                <div className="space-y-2">{e?.parents?.map(p => (
                  <div key={p.id} className="flex items-center gap-3 p-3 bg-neutral-50 rounded-xl border border-neutral-100">
                    <div className="w-9 h-9 bg-neutral-200 rounded-lg flex items-center justify-center text-neutral-600 font-bold flex-shrink-0">
                      {p.lien === 'père' ? '👨' : p.lien === 'mère' ? '👩' : '👤'}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-semibold">{p.nom} {p.prenom}</div>
                      <div className="text-xs text-neutral-400 capitalize">{p.lien}</div>
                    </div>
                    <div className="text-right">
                      <a href={`tel:${p.telephone_1}`} className="text-sm text-primary-600 font-medium hover:underline">{p.telephone_1}</a>
                    </div>
                  </div>
                ))}</div>
              </section>
            </div>
          )}

          {activeTab === 'notes' && !isLoading && <NotesList eleveId={e?.id} />}
          {activeTab === 'presences' && !isLoading && <PresencesList eleveId={e?.id} />}
          {activeTab === 'paiements' && !isLoading && <PaiementsList eleveId={e?.id} />}
          {activeTab === 'matching' && !isLoading && (
            <MatchingSuggestions
              eleveId={e?.id}
              onAssign={(enseignant) => toast.success(`${enseignant.prenom} ${enseignant.nom} proposé`)}
            />
          )}
        </div>

        <div className="p-4 border-t border-neutral-200 flex gap-2 flex-shrink-0">
          <a href={`tel:${e?.parents?.[0]?.telephone_1}`} className="btn btn-secondary flex-1 text-sm">📞 Appeler</a>
          <button onClick={onEdit} className="btn btn-primary flex-1 text-sm">✏️ Modifier</button>
        </div>
      </div>
    </>
  );
}

function NotesList({ eleveId }) {
  const [notes, setNotes] = useState(null);
  const [stats, setStats] = useState(null);
  const [moyenne, setMoyenne] = useState(null);

  useEffect(() => {
    if (!eleveId) return;
    api.get(`/eleves/${eleveId}/notes`).then(r => {
      setNotes(r.data?.notes || []);
      setStats(r.data);
      setMoyenne(r.data?.moyenne_generale);
    });
  }, [eleveId]);

  if (!notes) return <div className="flex justify-center py-8"><div className="animate-spin text-2xl">⏳</div></div>;

  return (
    <div className="space-y-4">
      <div className="bg-gradient-to-r from-primary-50 to-blue-50 rounded-xl p-4 border border-primary-100">
        <div className="flex items-center justify-between">
          <div>
            <div className="text-xs text-primary-600 font-semibold uppercase tracking-wider">Moyenne Générale</div>
            <div className="text-4xl font-bold text-primary-700 mt-1">{moyenne ?? '—'}/20</div>
          </div>
        </div>
      </div>
      <div className="space-y-2">
        {notes.length === 0 ? <p className="text-center text-neutral-400 py-8 text-sm">Aucune note</p>
          : notes.flatMap(matiere => matiere.notes?.map(n => (
            <div key={n.id} className="flex items-center gap-3 p-3 bg-neutral-50 rounded-xl">
              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium text-neutral-800 truncate">{matiere.matiere}</div>
                <div className="text-xs text-neutral-400">{n.date}</div>
              </div>
              <div className="text-right flex-shrink-0">
                <div className="text-lg font-bold text-neutral-800">{n.absent ? 'Abs.' : `${n.note}/${n.note_sur}`}</div>
              </div>
            </div>
          )))}
      </div>
    </div>
  );
}

function PresencesList({ eleveId }) {
  const [data, setData] = useState(null);
  useEffect(() => {
    if (!eleveId) return;
    api.get(`/eleves/${eleveId}/presences`, { params: { mois: new Date().getMonth() + 1, annee: new Date().getFullYear() } }).then(r => setData(r));
  }, [eleveId]);

  if (!data) return <div className="flex justify-center py-8"><div className="animate-spin text-2xl">⏳</div></div>;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-2">
        {[
          { label: 'Taux', value: `${data.stats?.taux_presence ?? 0}%`, icon: '📊' },
          { label: 'Présences', value: data.stats?.presents ?? 0, icon: '✅' },
          { label: 'Absences', value: data.stats?.absents ?? 0, icon: '❌' },
        ].map(s => (
          <div key={s.label} className="bg-neutral-50 rounded-xl p-3 text-center">
            <div className="text-xl mb-0.5">{s.icon}</div>
            <div className="text-lg font-bold text-neutral-800">{s.value}</div>
            <div className="text-xs text-neutral-400">{s.label}</div>
          </div>
        ))}
      </div>
      <div className="space-y-1.5 max-h-64 overflow-y-auto">
        {data.data?.map((p, i) => (
          <div key={i} className={`flex items-center gap-3 p-2.5 rounded-lg ${p.statut === 'présent' ? 'bg-green-100' : 'bg-red-100'}`}>
            <span>{p.statut === 'présent' ? '✅' : '❌'}</span>
            <div className="flex-1 text-xs">
              <span className="font-medium">{p.seance?.cours?.groupe?.matiere?.nom_fr ?? 'Cours'}</span>
              <span className="text-neutral-500 ml-2">{p.seance?.date_seance}</span>
            </div>
            <span className="text-xs font-semibold">{p.statut}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function PaiementsList({ eleveId }) {
  const [data, setData] = useState(null);
  useEffect(() => {
    if (!eleveId) return;
    api.get(`/eleves/${eleveId}/paiements`).then(r => setData(r));
  }, [eleveId]);

  if (!data) return <div className="flex justify-center py-8"><div className="animate-spin text-2xl">⏳</div></div>;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <div className="bg-green-50 rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-green-700">{Number(data.data?.financier?.total_paye ?? 0).toLocaleString()} DA</div>
          <div className="text-xs text-green-600">Total payé</div>
        </div>
        <div className="bg-red-50 rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-red-700">{Number(data.data?.financier?.total_dette ?? 0).toLocaleString()} DA</div>
          <div className="text-xs text-red-600">Impayés</div>
        </div>
      </div>
      <div className="space-y-2">
        {data.data?.factures?.length === 0 ? <p className="text-center text-neutral-400 py-6 text-sm">Aucun paiement</p>
          : data.data?.factures?.map(p => (
            <div key={p.id} className="flex items-center justify-between p-3 bg-neutral-50 rounded-xl">
              <div><div className="text-sm font-medium text-neutral-800">{p.numero_facture}</div><div className="text-xs text-neutral-400">{p.date_emission}</div></div>
              <div className="text-right">
                <div className="font-bold text-green-700">{Number(p.total_ttc).toLocaleString()} DA</div>
                <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${p.statut === 'payée' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>{p.statut}</span>
              </div>
            </div>
          ))}
      </div>
    </div>
  );
}
