import React, { useState, useEffect } from 'react';
import { Users, TrendingUp, TrendingDown, AlertCircle, DollarSign } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import KpiCard from '@components/ui/KpiCard';
import Card from '@components/ui/Card';
import Table from '@components/ui/Table';
import Badge from '@components/ui/Badge';
import Alert from '@components/ui/Alert';
import DonutChart from '@components/ui/DonutChart';
import BarChart from '@components/ui/BarChart';

let BASE_URL = import.meta.env.VITE_API_URL ?? '';
if (BASE_URL.endsWith('/api/v1')) BASE_URL = BASE_URL.slice(0, -'/api/v1'.length);

const headers = {
  headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` },
};

const ALERT_MAP = { danger: 'error', critical: 'error' };

export default function DashboardPage() {
  const navigate = useNavigate();
  const [stats, setStats] = useState(null);
  const [recentStudents, setRecentStudents] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch(`${BASE_URL}/api/v1/analytics/dashboard`, headers).then(r => r.json()),
      fetch(`${BASE_URL}/api/v1/eleves?per_page=5`, headers).then(r => r.json()),
    ])
      .then(([dashRes, elevesRes]) => {
        const d = dashRes?.data;
        setStats({
          total_eleves:          d?.kpis?.total_eleves ?? 0,
          evolution_eleves:      d?.kpis?.evolution_eleves ?? 0,
          ca_mois:               d?.kpis?.ca_mois ?? 0,
          evolution_ca_pct:      d?.kpis?.evolution_ca_pct ?? 0,
          impayes_montant:       d?.kpis?.impayes_montant ?? 0,
          impayes_nb:            d?.kpis?.impayes_nb ?? 0,
          impayes_critiques_nb:  d?.kpis?.impayes_critiques_nb ?? 0,
          taux_recouvrement:     d?.kpis?.taux_recouvrement ?? 0,
          seances_aujourd_hui:   d?.kpis?.seances_aujourd_hui ?? 0,
          absences_aujourd_hui:  d?.kpis?.absences_aujourd_hui ?? 0,
          ca_six_mois:           d?.graphiques?.ca_six_mois ?? [],
          top_matieres:          d?.graphiques?.top_matieres ?? [],
          assiduite:             d?.graphiques?.assiduite ?? [],
          alertes:               d?.alertes ?? [],
        });

        const raw = elevesRes?.data ?? [];
        setRecentStudents(
          raw.map(e => ({
            id:    e.id,
            nom:   `${e.nom ?? ''} ${e.prenom ?? ''}`.trim(),
            classe: e.niveau_scolaire ?? e.classe ?? '—',
            statut: e.statut ?? 'actif',
          }))
        );
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const fmt = n => new Intl.NumberFormat('fr-DZ').format(n ?? 0);

  const isEmpty = !loading && (stats?.total_eleves ?? 0) === 0;

  const chartData = (stats?.ca_six_mois ?? []).map(m => ({
    label: m.mois ?? '',
    value: m.valeur ?? 0,
  }));

  const tauxOccup = stats?.taux_recouvrement ?? 0;

  return (
    <div className="animate-fadeIn space-y-6" data-testid="dashboard">
      <div>
        <h1 className="text-xl font-extrabold text-text">Tableau de bord</h1>
        <p className="text-xs text-muted mt-1">
          {new Date().toLocaleDateString('fr-DZ', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
          })}
        </p>
      </div>

      {loading && (
        <div className="flex items-center justify-center py-12">
          <div className="w-8 h-8 border-2 border-border border-t-accent rounded-full animate-spin" />
        </div>
      )}

      {!loading && isEmpty && (
        <Card>
          <div className="text-center py-10">
            <p className="text-sm text-muted">Aucune donnée disponible. Ajoutez des élèves pour commencer.</p>
          </div>
        </Card>
      )}

      {!loading && !isEmpty && (
        <>
          {(stats?.alertes ?? []).length > 0 && (
            <div className="space-y-2">
              {stats.alertes.map((a, i) => (
                <Alert key={i} type={ALERT_MAP[a.type] ?? a.type} onDismiss={() => {}}>
                  <span className="mr-1">{a.icone}</span>
                  <strong>{a.message}</strong>
                  {a.action && (
                    <button
                      className="ml-2 underline text-accent cursor-pointer"
                      onClick={() => navigate(a.route ?? '/')}
                    >
                      {a.action}
                    </button>
                  )}
                </Alert>
              ))}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <KpiCard
              icon={Users}
              label="Élèves actifs"
              value={fmt(stats?.total_eleves)}
              color="#10B981"
              trend={stats?.evolution_eleves ?? 0}
              trendUp={(stats?.evolution_eleves ?? 0) >= 0}
            />
            <KpiCard
              icon={DollarSign}
              label="CA ce mois"
              value={fmt(stats?.ca_mois) + ' DA'}
              color="#2563EB"
              trend={stats?.evolution_ca_pct ?? 0}
              trendUp={(stats?.evolution_ca_pct ?? 0) >= 0}
            />
            <KpiCard
              icon={AlertCircle}
              label="Impayés"
              value={fmt(stats?.impayes_montant) + ' DA'}
              sub={`${stats?.impayes_nb ?? 0} facture(s)`}
              color="#EF4444"
            />
            <KpiCard
              icon={TrendingUp}
              label="Séances aujourd'hui"
              value={fmt(stats?.seances_aujourd_hui)}
              color="#F59E0B"
              sub={`${stats?.absences_aujourd_hui ?? 0} absence(s)`}
            />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card className="lg:col-span-2">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-xs font-bold text-accent uppercase tracking-wider">
                  Évolution CA — 6 derniers mois
                </h3>
                {(stats?.evolution_ca_pct ?? 0) !== 0 && (
                  <Badge type={(stats?.evolution_ca_pct ?? 0) > 0 ? 'success' : 'danger'}>
                    {(stats?.evolution_ca_pct ?? 0) > 0 ? '+' : ''}{stats?.evolution_ca_pct ?? 0}% vs N-1
                  </Badge>
                )}
              </div>
              {chartData.length > 0 ? (
                <BarChart data={chartData} height={160} />
              ) : (
                <div className="h-40 flex items-center justify-center text-xs text-muted">
                  Pas encore de données financières
                </div>
              )}
            </Card>

            <Card>
              <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-4">
                Taux de recouvrement
              </h3>
              <div className="flex justify-center">
                <DonutChart
                  value={tauxOccup}
                  size={120}
                  stroke={8}
                  label={tauxOccup + '%'}
                  sub="Recouvrement"
                />
              </div>
            </Card>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card className="lg:col-span-2" padding={false}>
              <div className="px-5 pt-4 pb-2">
                <h3 className="text-xs font-bold text-accent uppercase tracking-wider">
                  Derniers élèves inscrits
                </h3>
              </div>
              {recentStudents.length > 0 ? (
                <Table
                  headers={['Nom', 'Classe', 'Statut']}
                  rows={recentStudents.map(s => ({
                    cells: [
                      <span className="font-semibold">{s.nom}</span>,
                      s.classe,
                      <Badge type={s.statut === 'actif' ? 'success' : 'danger'}>
                        {s.statut === 'actif' ? 'Actif' : 'Inactif'}
                      </Badge>,
                    ],
                  }))}
                />
              ) : (
                <div className="px-5 py-6 text-xs text-muted text-center">Aucun élève</div>
              )}
            </Card>

            <Card>
              <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-3">
                Actions rapides
              </h3>
              <div className="space-y-1">
                {[
                  { label: 'Nouvel élève',     path: '/eleves',   color: '#10B981' },
                  { label: 'Déclarer absence', path: '/absences', color: '#F59E0B' },
                  { label: 'Émettre facture',  path: '/factures', color: '#2563EB' },
                  { label: 'Voir planning',    path: '/planning', color: '#06B6D4' },
                ].map(a => (
                  <button
                    key={a.path}
                    onClick={() => navigate(a.path)}
                    className="w-full flex items-center gap-2.5 px-3 py-[9px] rounded-lg bg-surface2 border border-border text-xs font-semibold text-text hover:bg-surface3 hover:border-border2 transition-all cursor-pointer text-left"
                  >
                    <span
                      className="w-6 h-6 rounded-md flex items-center justify-center"
                      style={{ background: a.color + '22' }}
                    >
                      <svg
                        width="12" height="12" viewBox="0 0 24 24" fill="none"
                        stroke={a.color} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"
                      >
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                      </svg>
                    </span>
                    {a.label}
                  </button>
                ))}
              </div>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}
