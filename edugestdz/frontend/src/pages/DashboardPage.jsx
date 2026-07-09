import { useState, useEffect } from 'react';
import { Users, TrendingUp, AlertCircle, DollarSign } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import KpiCard from '@components/ui/KpiCard';
import Card from '@components/ui/Card';
import Table from '@components/ui/Table';
import Badge from '@components/ui/Badge';
import Alert from '@components/ui/Alert';
import DonutChart from '@components/ui/DonutChart';
import BarChart from '@components/ui/BarChart';

const api = (path) => fetch(`/api/v1${path}`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
}).then(r => r.json());

const MONTHS = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];

export default function DashboardPage() {
  const navigate = useNavigate();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      api('/eleves?per_page=1'),
      api('/finance/tableau-bord'),
      api('/absences?date=' + new Date().toISOString().split('T')[0]),
      api('/planning/aujourd-hui'),
    ]).then(([elevesRes, financeRes, absencesRes, planningRes]) => {
      setStats({
        total_eleves:  elevesRes?.meta?.total ?? 0,
        eleves_actifs: elevesRes?.meta?.total ?? 0,
        ca_mois:       financeRes?.data?.ca_mois ?? 0,
        impayes:       financeRes?.data?.impayes ?? 0,
        nb_impayes:    financeRes?.data?.nb_impayes ?? 0,
        absences_jour: absencesRes?.meta?.total ?? 0,
        seances_jour:  planningRes?.data?.total ?? 0,
      });
    }).catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const fmt = (n) => new Intl.NumberFormat('fr-DZ').format(n ?? 0);

  const chartData = MONTHS.slice(0, 6).map((m, i) => ({
    label: m,
    value: stats ? Math.floor((stats.ca_mois || 50000) * (0.5 + i * 0.15)) : 0,
  }));

  const recentStudents = [
    { name: 'Benali Mohamed', classe: '3AS', cours: 'Maths', statut: 'present', montant: '15 000 DA' },
    { name: 'Ziani Sarah',    classe: '2AS', cours: 'Physique', statut: 'absent', montant: '12 000 DA' },
    { name: 'Ouali Amine',    classe: '1AS', cours: 'Français', statut: 'present', montant: '10 000 DA' },
    { name: 'Mekki Ines',     classe: '4AM', cours: 'Anglais',  statut: 'retard', montant: '8 000 DA' },
  ];

  return (
    <div className="animate-fadeIn space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-extrabold text-text">Tableau de bord</h1>
        <p className="text-xs text-muted mt-1">
          {new Date().toLocaleDateString('fr-DZ', { weekday:'long', year:'numeric', month:'long', day:'numeric' })}
        </p>
      </div>

      {/* Alert */}
      {stats?.impayes > 0 && (
        <Alert type="warning" onDismiss={() => {}}>
          <strong>{fmt(stats.nb_impayes)} facture(s) impayée(s)</strong> — montant total de <strong>{fmt(stats.impayes)} DA</strong>
        </Alert>
      )}

      {/* KPI Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <KpiCard
          icon={Users}
          label="Élèves actifs"
          value={loading ? '...' : fmt(stats?.eleves_actifs)}
          color="#10B981"
          trend={12}
          trendUp
        />
        <KpiCard
          icon={DollarSign}
          label="CA ce mois"
          value={loading ? '...' : fmt(stats?.ca_mois) + ' DA'}
          color="#2563EB"
          trend={8}
          trendUp
        />
        <KpiCard
          icon={AlertCircle}
          label="Impayés"
          value={loading ? '...' : fmt(stats?.impayes) + ' DA'}
          sub={`${stats?.nb_impayes ?? 0} facture(s)`}
          color="#EF4444"
          trend={5}
        />
        <KpiCard
          icon={TrendingUp}
          label="Séances aujourd'hui"
          value={loading ? '...' : fmt(stats?.seances_jour)}
          color="#F59E0B"
          sub={`${stats?.absences_jour ?? 0} absence(s)`}
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Bar chart */}
        <Card className="lg:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xs font-bold text-accent uppercase tracking-wider">Évolution CA — 6 derniers mois</h3>
            <Badge type="info">+22% vs N-1</Badge>
          </div>
          {loading ? (
            <div className="h-40 flex items-center justify-center">
              <div className="w-6 h-6 border-2 border-border border-t-accent rounded-full animate-spin" />
            </div>
          ) : (
            <BarChart data={chartData} height={160} />
          )}
        </Card>

        {/* Donut */}
        <Card>
          <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-4">Taux d'occupation</h3>
          <div className="flex justify-center">
            <DonutChart value={78} size={120} stroke={8} label="Salles" sub="12 / 15 occupées" />
          </div>
        </Card>
      </div>

      {/* Bottom row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Recent students */}
        <Card className="lg:col-span-2" padding={false}>
          <div className="px-5 pt-4 pb-2">
            <h3 className="text-xs font-bold text-accent uppercase tracking-wider">Derniers élèves inscrits</h3>
          </div>
          <Table
            headers={['Nom', 'Classe', 'Cours', 'Statut', 'Mensualité']}
            rows={recentStudents.map(s => ({
              cells: [
                <span className="font-semibold">{s.name}</span>,
                s.classe,
                s.cours,
                <Badge type={s.statut === 'present' ? 'success' : s.statut === 'retard' ? 'warning' : 'danger'}>
                  {s.statut === 'present' ? 'Présent' : s.statut === 'retard' ? 'Retard' : 'Absent'}
                </Badge>,
                s.montant,
              ],
            }))}
          />
        </Card>

        {/* Quick actions */}
        <Card>
          <h3 className="text-xs font-bold text-accent uppercase tracking-wider mb-3">Actions rapides</h3>
          <div className="space-y-1">
            {[
              { label: 'Nouvel élève',     path: '/eleves',     color: '#10B981' },
              { label: 'Déclarer absence', path: '/absences',   color: '#F59E0B' },
              { label: 'Émettre facture',  path: '/factures',   color: '#2563EB' },
              { label: 'Voir planning',    path: '/planning',   color: '#06B6D4' },
            ].map(a => (
              <button
                key={a.path}
                onClick={() => navigate(a.path)}
                className="w-full flex items-center gap-2.5 px-3 py-[9px] rounded-lg bg-surface2 border border-border text-xs font-semibold text-text hover:bg-surface3 hover:border-border2 transition-all cursor-pointer text-left"
              >
                <span className="w-6 h-6 rounded-md flex items-center justify-center"
                  style={{ background: a.color + '22' }}>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={a.color} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                </span>
                {a.label}
              </button>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
