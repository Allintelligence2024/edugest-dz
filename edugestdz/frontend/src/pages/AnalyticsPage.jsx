import { useState, useEffect, useCallback } from 'react';
import { FileText, TrendingUp, Users, AlertTriangle, Download, RefreshCw } from 'lucide-react';
import KpiCard from '@components/ui/KpiCard';
import Card from '@components/ui/Card';
import Badge from '@components/ui/Badge';
import BarChart from '@components/ui/BarChart';

const api = (path) =>
  fetch(`/api/v1${path}`, {
    headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
  }).then((r) => r.json());

const fmt = (n) => new Intl.NumberFormat('fr-DZ').format(n ?? 0);
const pct = (n) => `${n ?? 0}%`;

export default function AnalyticsPage() {
  const [dashboard, setDashboard] = useState(null);
  const [finances,  setFinances]  = useState(null);
  const [loading,   setLoading]   = useState(true);
  const [refresh,   setRefresh]   = useState(0);
  const [pdfLoading, setPdfLoading] = useState(false);

  const charger = useCallback(async () => {
    setLoading(true);
    try {
      const [dashRes, finRes] = await Promise.all([
        api('/analytics/dashboard'),
        api('/analytics/finances'),
      ]);
      if (dashRes.success) setDashboard(dashRes.data);
      if (finRes.success)  setFinances(finRes.data);
    } catch (err) {
      console.error('Erreur chargement analytics:', err);
    } finally {
      setLoading(false);
    }
  }, [refresh]);

  useEffect(() => { charger(); }, [charger]);

  const telechargerPdf = async () => {
    setPdfLoading(true);
    try {
      const res = await fetch('/api/v1/analytics/rapport-pdf', {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
      });
      const blob = await res.blob();
      const url  = window.URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href     = url;
      a.download = `rapport-mensuel-${new Date().toISOString().slice(0, 7)}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      alert('Erreur lors de la génération du PDF');
    } finally {
      setPdfLoading(false);
    }
  };

  const kpis = dashboard?.kpis ?? {};
  const graphiques = dashboard?.graphiques ?? {};

  const caChartData = (graphiques.ca_six_mois ?? []).map((m) => ({
    label: m.mois,
    value: m.valeur,
  }));

  return (
    <div className="animate-fadeIn space-y-6">

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-extrabold text-text">Analytics — Tableau de bord Directeur</h1>
          <p className="text-xs text-muted mt-1">
            {dashboard?.periode ?? 'Chargement...'} · Mis à jour toutes les 15 minutes
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setRefresh(r => r + 1)}
            disabled={loading}
            className="flex items-center gap-2 px-3 py-2 text-xs font-semibold
                       bg-surface2 border border-border rounded-lg text-muted
                       hover:text-text hover:border-accent transition-colors"
          >
            <RefreshCw size={13} className={loading ? 'animate-spin' : ''} />
            Actualiser
          </button>
          <button
            onClick={telechargerPdf}
            disabled={pdfLoading}
            className="flex items-center gap-2 px-4 py-2 text-xs font-semibold
                       bg-accent text-white rounded-lg
                       hover:bg-blue-700 transition-colors shadow"
          >
            <FileText size={13} />
            {pdfLoading ? 'Génération...' : 'Rapport PDF'}
          </button>
        </div>
      </div>

      {(dashboard?.alertes ?? []).length > 0 && (
        <div className="space-y-2">
          {dashboard.alertes.map((alerte, i) => (
            <div
              key={i}
              className={`flex items-center gap-3 p-3 rounded-xl border text-sm font-medium
                ${alerte.type === 'danger'
                  ? 'bg-red-500/5 border-red-500/25 text-red-400'
                  : 'bg-amber-500/5 border-amber-500/25 text-amber-400'
                }`}
            >
              <AlertTriangle size={16} className="flex-shrink-0" />
              <span className="flex-1">{alerte.message}</span>
              <Badge type={alerte.type === 'danger' ? 'error' : 'warning'}>
                Priorité {alerte.priorite}
              </Badge>
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <KpiCard
          icon={Users}
          label="Élèves actifs"
          value={loading ? '...' : fmt(kpis.total_eleves)}
          trend={kpis.evolution_eleves}
          trendUp={(kpis.evolution_eleves ?? 0) >= 0}
          color="#10B981"
        />
        <KpiCard
          icon={TrendingUp}
          label="CA ce mois"
          value={loading ? '...' : `${fmt(kpis.ca_mois)} DA`}
          trend={kpis.evolution_ca_pct}
          trendUp={(kpis.evolution_ca_pct ?? 0) >= 0}
          sub="vs mois précédent"
          color="#2563EB"
        />
        <KpiCard
          label="Taux recouvrement"
          value={loading ? '...' : pct(kpis.taux_recouvrement)}
          color={kpis.taux_recouvrement >= 80 ? '#10B981' : '#EF4444'}
          sub="Encaissé / Facturé"
        />
        <KpiCard
          label="Impayés critiques"
          value={loading ? '...' : fmt(kpis.impayes_critiques_nb)}
          sub={`${fmt(kpis.impayes_montant)} DA total`}
          color="#EF4444"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <Card className="lg:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xs font-bold text-accent uppercase tracking-wider">
              Évolution CA — 6 derniers mois (données réelles)
            </h3>
            <Badge type="success">BDD en temps réel</Badge>
          </div>
          {loading ? (
            <div className="h-40 flex items-center justify-center">
              <div className="w-6 h-6 border-2 border-border border-t-accent rounded-full animate-spin" />
            </div>
          ) : (
            <BarChart data={caChartData} height={160} />
          )}
        </Card>

        <Card>
          <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-4">
            Assiduité — 4 dernières semaines
          </h3>
          {loading ? (
            <div className="h-40 flex items-center justify-center">
              <div className="w-6 h-6 border-2 border-border border-t-accent rounded-full animate-spin" />
            </div>
          ) : (
            <div className="space-y-3">
              {(graphiques.assiduite ?? []).map((s, i) => (
                <div key={i}>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-text font-medium">{s.semaine} ({s.debut})</span>
                    <span
                      className={`font-bold ${
                        s.taux_presence >= 85 ? 'text-green-400' :
                        s.taux_presence >= 70 ? 'text-amber-400' : 'text-red-400'
                      }`}
                    >
                      {s.taux_presence}%
                    </span>
                  </div>
                  <div className="h-2 bg-surface2 rounded-full overflow-hidden">
                    <div
                      className="h-full rounded-full transition-all"
                      style={{
                        width: `${s.taux_presence}%`,
                        background: s.taux_presence >= 85 ? '#10B981' :
                                    s.taux_presence >= 70 ? '#F59E0B' : '#EF4444',
                      }}
                    />
                  </div>
                  <p className="text-[10px] text-muted mt-1">{s.absences} absence(s)</p>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <Card>
          <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-4">
            Top 5 matières — Meilleures moyennes
          </h3>
          {loading ? (
            <div className="space-y-3">
              {[1,2,3,4,5].map(i => (
                <div key={i} className="h-8 bg-surface2 rounded animate-pulse" />
              ))}
            </div>
          ) : (
            <div className="space-y-3">
              {(graphiques.top_matieres ?? []).map((m, i) => (
                <div key={i}>
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-text font-medium flex items-center gap-2">
                      <span className="text-muted">#{i + 1}</span>
                      {m.matiere}
                    </span>
                    <span className="font-bold text-accent">{m.moyenne}/20</span>
                  </div>
                  <div className="h-1.5 bg-surface2 rounded-full overflow-hidden">
                    <div
                      className="h-full rounded-full bg-accent"
                      style={{ width: `${(m.moyenne / 20) * 100}%` }}
                    />
                  </div>
                </div>
              ))}
              {(graphiques.top_matieres ?? []).length === 0 && (
                <p className="text-xs text-muted text-center py-4">Aucune note saisie</p>
              )}
            </div>
          )}
        </Card>

        <Card>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xs font-bold text-accent uppercase tracking-wider">
              Impayés urgents (&gt;30j)
            </h3>
            {!loading && (finances?.impayes_urgents ?? []).length > 0 && (
              <Badge type="error">{finances.impayes_urgents.length} cas</Badge>
            )}
          </div>
          {loading ? (
            <div className="space-y-2">
              {[1,2,3].map(i => <div key={i} className="h-10 bg-surface2 rounded animate-pulse" />)}
            </div>
          ) : (finances?.impayes_urgents ?? []).length === 0 ? (
            <div className="text-center py-6">
              <p className="text-2xl mb-2">✅</p>
              <p className="text-xs text-muted">Aucun impayé critique</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {(finances.impayes_urgents ?? []).slice(0, 5).map((f, i) => (
                <div
                  key={i}
                  className="flex items-center justify-between p-2.5 bg-surface2 rounded-lg"
                >
                  <div>
                    <p className="text-xs font-semibold text-text">{f.eleve_nom}</p>
                    <p className="text-[10px] text-muted">{f.numero_facture}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs font-bold text-text">{fmt(f.total_ttc)} DA</p>
                    <p className="text-[10px] text-red-400 font-semibold">
                      {f.jours_retard}j de retard
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>

    </div>
  );
}
