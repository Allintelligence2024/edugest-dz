import React, { useState, useEffect } from 'react';
import { toast } from 'react-hot-toast';
import { matchingApi } from '@api/matching.api';

export default function MatchingSuggestions({ eleveId, onAssign }) {
  const [suggestions, setSuggestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [llmUsed, setLlmUsed] = useState(false);

  useEffect(() => {
    if (!eleveId) return;
    setLoading(true);
    matchingApi
      .suggestions(eleveId)
      .then((res) => {
        setSuggestions(res.data || []);
        setLlmUsed(res.meta?.llm_used || false);
      })
      .catch(() => toast.error('Erreur chargement suggestions'))
      .finally(() => setLoading(false));
  }, [eleveId]);

  const initials = (ens) => ((ens?.prenom?.[0] || '') + (ens?.nom?.[0] || '')).toUpperCase();

  const fullName = (ens) => [ens?.prenom, ens?.nom].filter(Boolean).join(' ');

  const scoreToStars = (score) => {
    const stars = Math.round(score * 5);
    return '★'.repeat(stars) + '☆'.repeat(5 - stars);
  };

  const scorePercent = (score) => `${Math.round(score * 100)}%`;

  if (loading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="bg-white rounded-xl border border-neutral-200 p-4 animate-pulse">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-full bg-neutral-200" />
              <div className="flex-1 space-y-2">
                <div className="h-4 bg-neutral-200 rounded w-1/3" />
                <div className="h-3 bg-neutral-100 rounded w-1/4" />
              </div>
              <div className="h-8 w-14 bg-neutral-200 rounded-full" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (!suggestions.length) {
    return (
      <div className="text-center py-8">
        <div className="text-4xl mb-2">🔍</div>
        <p className="text-neutral-500 text-sm">Aucun enseignant trouvé</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-bold text-neutral-700">Suggestions IA</h3>
        {llmUsed && (
          <span className="text-[10px] px-2 py-0.5 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 text-white font-semibold">
            LLM
          </span>
        )}
      </div>

      <div className="space-y-2">
        {suggestions.map((s, idx) => {
          const ens = s.enseignant;
          return (
            <div
              key={ens.id || idx}
              className="bg-white rounded-xl border border-neutral-200 p-4 hover:border-primary-200 transition-colors"
            >
              <div className="flex items-start gap-3">
                <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-sm flex-shrink-0">
                  {ens.photo_url ? (
                    <img src={ens.photo_url} className="w-full h-full rounded-full object-cover" alt="" />
                  ) : (
                    initials(ens)
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2">
                    <div>
                      <div className="text-sm font-semibold text-neutral-800 truncate">{fullName(ens)}</div>
                      <div className="text-xs text-neutral-400">{ens.matieres?.map((m) => m.nom_fr).join(', ') || '—'}</div>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <div className="text-sm font-bold text-primary-600">{scorePercent(s.score)}</div>
                      <div className="text-[10px] text-amber-500 tracking-wider">{scoreToStars(s.score)}</div>
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-2 mt-2">
                    {ens.taux_horaire && (
                      <span className="text-[10px] bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-medium">
                        {ens.taux_horaire} DA/h
                      </span>
                    )}
                    {ens.wilaya?.nom_fr && (
                      <span className="text-[10px] bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                        📍 {ens.wilaya.nom_fr}
                      </span>
                    )}
                    {ens.experience_annees != null && (
                      <span className="text-[10px] bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full font-medium">
                        {ens.experience_annees} ans
                      </span>
                    )}
                  </div>

                  {s.raisons?.length > 0 && (
                    <ul className="mt-2 space-y-0.5">
                      {s.raisons.map((r, i) => (
                        <li key={i} className="text-[11px] text-neutral-500 flex items-start gap-1">
                          <span className="text-primary-400 mt-0.5">•</span>
                          {r}
                        </li>
                      ))}
                    </ul>
                  )}

                  {s.justification_llm && (
                    <p className="mt-2 text-[11px] italic text-purple-600 bg-purple-50 rounded-lg px-2 py-1">
                      {s.justification_llm}
                    </p>
                  )}
                </div>
              </div>

              {onAssign && (
                <button
                  onClick={() => onAssign(ens)}
                  className="mt-3 w-full text-xs py-2 rounded-lg border border-primary-200 text-primary-700 font-medium hover:bg-primary-50 transition-colors"
                >
                  Proposer cet enseignant
                </button>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
