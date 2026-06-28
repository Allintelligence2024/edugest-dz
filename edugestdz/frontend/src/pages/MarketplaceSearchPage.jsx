import React, { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { marketplaceApi } from '@api/marketplace.api';
import SearchBar from '@components/common/SearchBar';
import Pagination from '@components/common/Pagination';

const TYPE_COURS_OPTIONS = [
  { value: 'presentiel', label: 'Présentiel' },
  { value: 'en_ligne', label: 'En ligne' },
  { value: 'les_deux', label: 'Les deux' },
];

const NIVEAUX = ['1AP','2AP','3AP','4AP','5AP','1AM','2AM','3AM','4AM','1AS','2AS','3AS','universitaire'];

function StarRating({ note }) {
  if (!note) return <span className="text-neutral-400 text-sm">—</span>;
  const full = Math.floor(note);
  const stars = Array.from({ length: 5 }, (_, i) => i < full ? '★' : '☆');
  return <span className="text-amber-400 text-sm">{stars.join('')} {note.toFixed(1)}</span>;
}

export default function MarketplaceSearchPage() {
  const [offres, setOffres] = useState([]);
  const [meta, setMeta] = useState({ total: 0, last_page: 1 });
  const [isLoading, setIsLoading] = useState(false);
  const [filters, setFilters] = useState({
    wilaya_id: '', matiere_id: '', niveau: '',
    tarif_min: '', tarif_max: '', type_cours: '', q: '',
  });
  const [page, setPage] = useState(1);
  const [matieres, setMatieres] = useState([]);
  const [wilayas, setWilayas] = useState([]);

  useEffect(() => {
    const loadRefs = async () => {
      try {
        const api = (await import('@api/axiosInstance')).default;
        const [matRes, wilRes] = await Promise.all([
          api.get('/matieres', { params: { per_page: 200 } }),
          api.get('/parametres/wilayas'),
        ]);
        setMatieres(matRes.data || []);
        setWilayas(wilRes.data || []);
      } catch { /* ignore */ }
    };
    loadRefs();
  }, []);

  const loadOffres = useCallback(async () => {
    setIsLoading(true);
    try {
      const params = { page, per_page: 12 };
      Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
      const res = await marketplaceApi.searchOffres(params);
      setOffres(res.data || []);
      setMeta(res.meta || { total: 0, last_page: 1 });
    } catch { setOffres([]); }
    finally { setIsLoading(false); }
  }, [page, filters]);

  useEffect(() => { loadOffres(); }, [loadOffres]);

  const handleFilterChange = (key, value) => {
    setFilters(f => ({ ...f, [key]: value }));
    setPage(1);
  };

  return (
    <div className="min-h-screen bg-neutral-50">
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-neutral-800">Marketplace</h1>
          <p className="text-neutral-500 mt-1">Trouvez le cours particulier idéal</p>
        </div>

        <div className="card p-5 mb-8 space-y-4">
          <SearchBar
            value={filters.q}
            onChange={v => handleFilterChange('q', v)}
            placeholder="Rechercher une matière, un niveau..."
          />
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <select value={filters.matiere_id} onChange={e => handleFilterChange('matiere_id', e.target.value)}
              className="input text-sm">
              <option value="">Matière</option>
              {matieres.map(m => <option key={m.id} value={m.id}>{m.nom_fr}</option>)}
            </select>
            <select value={filters.niveau} onChange={e => handleFilterChange('niveau', e.target.value)}
              className="input text-sm">
              <option value="">Niveau</option>
              {NIVEAUX.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
            <select value={filters.wilaya_id} onChange={e => handleFilterChange('wilaya_id', e.target.value)}
              className="input text-sm">
              <option value="">Wilaya</option>
              {wilayas.map(w => <option key={w.id} value={w.id}>{w.nom_fr}</option>)}
            </select>
            <select value={filters.type_cours} onChange={e => handleFilterChange('type_cours', e.target.value)}
              className="input text-sm">
              <option value="">Type</option>
              {TYPE_COURS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            <input type="number" placeholder="Tarif min" value={filters.tarif_min}
              onChange={e => handleFilterChange('tarif_min', e.target.value)}
              className="input text-sm" />
            <input type="number" placeholder="Tarif max" value={filters.tarif_max}
              onChange={e => handleFilterChange('tarif_max', e.target.value)}
              className="input text-sm" />
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-16">
            <div className="animate-spin w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full" />
          </div>
        ) : offres.length === 0 ? (
          <div className="text-center py-16 text-neutral-400">
            <p className="text-5xl mb-3">🔍</p>
            <p className="text-lg">Aucune offre trouvée</p>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {offres.map(offre => (
                <Link key={offre.id} to={`/marketplace/offres/${offre.id}`}
                  className="card p-5 hover:shadow-lg transition-shadow block">
                  <div className="flex items-start gap-3 mb-3">
                    <div className="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-lg shrink-0">
                      {offre.enseignant?.prenom?.[0]}{offre.enseignant?.nom?.[0]}
                    </div>
                    <div className="min-w-0">
                      <p className="font-semibold text-neutral-800 truncate">
                        {offre.enseignant ? `${offre.enseignant.prenom} ${offre.enseignant.nom}` : 'Centre'}
                      </p>
                      <p className="text-sm text-neutral-500">{offre.matiere?.nom_fr} — {offre.niveau}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 mb-2">
                    <span className="badge badge-primary">{offre.type_cours === 'en_ligne' ? 'En ligne' : offre.type_cours === 'presentiel' ? 'Présentiel' : 'Mixte'}</span>
                    {offre.wilaya && <span className="text-xs text-neutral-400">📍 {offre.wilaya.nom_fr}</span>}
                  </div>

                  <p className="text-sm text-neutral-600 line-clamp-2 mb-3">{offre.description}</p>

                  <div className="flex items-center justify-between">
                    <div>
                      <span className="text-lg font-bold text-primary-700">{offre.tarif_seance.toLocaleString('fr-DZ')} DA</span>
                      <span className="text-xs text-neutral-400">/ séance</span>
                    </div>
                    <div className="flex items-center gap-2">
                      {offre.places_restantes > 0 ? (
                        <span className="badge badge-success">{offre.places_restantes} place{offre.places_restantes > 1 ? 's' : ''}</span>
                      ) : (
                        <span className="badge badge-error">Complet</span>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
            <div className="mt-6">
              <Pagination
                currentPage={page}
                lastPage={meta.last_page}
                total={meta.total}
                perPage={12}
                onPageChange={setPage} />
            </div>
          </>
        )}
      </div>
    </div>
  );
}
