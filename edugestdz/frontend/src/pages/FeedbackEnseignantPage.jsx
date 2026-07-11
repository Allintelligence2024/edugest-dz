import React, { useState, useEffect, useCallback } from 'react';
import api from '@api/axiosInstance';
import toast from 'react-hot-toast';

export default function FeedbackEnseignantPage() {
  const [enseignants, setEnseignants] = useState([]);
  const [selectedId, setSelectedId] = useState('');
  const [sujet, setSujet] = useState('');
  const [message, setMessage] = useState('');
  const [note, setNote] = useState(0);
  const [hoveredStar, setHoveredStar] = useState(0);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [envoyes, setEnvoyes] = useState([]);

  const fetchEnseignants = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get('/enseignants', { params: { per_page: 100 } });
      const list = (res.data || []).map(e => ({
        id: e.id || e.user_id,
        nom: e.nom,
        prenom: e.prenom,
        matiere: e.matiere || e.specialite || '',
      }));
      setEnseignants(list);
    } catch {
      setEnseignants([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchEnseignants(); }, [fetchEnseignants]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedId || !message.trim()) {
      toast.error('Veuillez sélectionner un enseignant et écrire un message');
      return;
    }
    try {
      setSending(true);
      await api.post('/feedback-enseignant', {
        enseignant_id: selectedId,
        sujet: sujet || 'Feedback',
        message,
        note,
      });
      toast.success('Feedback envoyé avec succès');
      setEnvoyes(prev => [...prev, { enseignant_id: selectedId, date: new Date() }]);
      setSelectedId('');
      setSujet('');
      setMessage('');
      setNote(0);
    } catch {
      toast.error("Erreur lors de l'envoi du feedback");
    } finally {
      setSending(false);
    }
  };

  const selectedEnseignant = enseignants.find(e => String(e.id) === String(selectedId));
  const alreadySent = envoyes.some(e => String(e.enseignant_id) === String(selectedId));

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-3 border-border rounded-full animate-spin" style={{ borderTopColor: 'var(--accent)' }} />
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-text">Feedback Enseignant</h1>
        <p className="text-muted text-sm mt-1">Donnez votre avis sur un enseignant</p>
      </div>

      <form onSubmit={handleSubmit} className="bg-surface rounded-2xl border border-border p-6 space-y-5">
        <div>
          <label className="block text-xs font-bold text-text2 mb-1.5">Enseignant</label>
          <select
            value={selectedId}
            onChange={e => setSelectedId(e.target.value)}
            className="w-full px-4 py-2.5 rounded-xl text-sm text-text transition-colors"
            style={{ background: 'var(--input-bg)', border: '1px solid var(--input-border)' }}
          >
            <option value="">— Sélectionner un enseignant —</option>
            {enseignants.map(e => (
              <option key={e.id} value={e.id}>
                {e.prenom} {e.nom}{e.matiere ? ` (${e.matiere})` : ''}
              </option>
            ))}
          </select>
        </div>

        {enseignants.length === 0 && (
          <p className="text-xs text-muted italic">Aucun enseignant disponible pour le moment.</p>
        )}

        {selectedEnseignant && alreadySent && (
          <div className="px-3 py-2 rounded-lg text-xs" style={{ background: 'var(--orange)18', color: 'var(--orange)' }}>
            Vous avez déjà envoyé un feedback pour cet enseignant aujourd'hui.
          </div>
        )}

        <div>
          <label className="block text-xs font-bold text-text2 mb-1.5">Note</label>
          <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map(star => (
              <button
                key={star}
                type="button"
                onClick={() => setNote(star)}
                onMouseEnter={() => setHoveredStar(star)}
                onMouseLeave={() => setHoveredStar(0)}
                className="w-10 h-10 rounded-lg flex items-center justify-center transition-colors"
                style={{
                  background: (hoveredStar || note) >= star ? 'var(--orange)18' : 'var(--surface2)',
                }}
              >
                <svg
                  width="20" height="20" viewBox="0 0 24 24"
                  fill={(hoveredStar || note) >= star ? 'var(--orange)' : 'none'}
                  stroke="var(--orange)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                >
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
              </button>
            ))}
            {note > 0 && <span className="text-xs text-muted ml-2 self-center">{note}/5</span>}
          </div>
        </div>

        <div>
          <label className="block text-xs font-bold text-text2 mb-1.5">Sujet (optionnel)</label>
          <input
            value={sujet}
            onChange={e => setSujet(e.target.value)}
            placeholder="Ex: Pédagogie, Ponctualité, Communication..."
            className="w-full px-4 py-2.5 rounded-xl text-sm text-text transition-colors"
            style={{ background: 'var(--input-bg)', border: '1px solid var(--input-border)' }}
          />
        </div>

        <div>
          <label className="block text-xs font-bold text-text2 mb-1.5">Message</label>
          <textarea
            value={message}
            onChange={e => setMessage(e.target.value)}
            rows={5}
            placeholder="Décrivez votre expérience avec cet enseignant..."
            className="w-full px-4 py-2.5 rounded-xl text-sm text-text resize-none transition-colors"
            style={{ background: 'var(--input-bg)', border: '1px solid var(--input-border)' }}
          />
        </div>

        <button
          type="submit"
          disabled={sending || !selectedId || !message.trim()}
          className="w-full py-3 rounded-xl text-sm font-bold text-white transition-colors disabled:opacity-50"
          style={{ background: 'var(--accent)' }}
        >
          {sending ? 'Envoi en cours...' : 'Envoyer le feedback'}
        </button>
      </form>
    </div>
  );
}
