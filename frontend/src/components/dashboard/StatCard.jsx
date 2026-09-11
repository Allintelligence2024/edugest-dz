import React from 'react';

const COLORS = {
  blue:   { bg: 'bg-blue-50',   icon: 'bg-blue-500',   text: 'text-blue-700'  },
  green:  { bg: 'bg-green-50',  icon: 'bg-green-500',  text: 'text-green-700' },
  orange: { bg: 'bg-orange-50', icon: 'bg-orange-500', text: 'text-orange-700'},
  red:    { bg: 'bg-red-50',    icon: 'bg-red-500',    text: 'text-red-700'   },
  purple: { bg: 'bg-purple-50', icon: 'bg-purple-500', text: 'text-purple-700'},
};

export default function StatCard({ icon, label, value, sub, color = 'blue', trend }) {
  const c = COLORS[color] || COLORS.blue;

  return (
    <div className="bg-white rounded-2xl border border-neutral-200 p-5 shadow-card hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wider">{label}</p>
          <p className="text-3xl font-bold text-neutral-800 mt-1">{value}</p>
          {sub && <p className={`text-xs mt-1 font-medium ${c.text}`}>{sub}</p>}
        </div>
        <div className={`w-12 h-12 ${c.icon} rounded-2xl flex items-center justify-center text-white text-2xl flex-shrink-0`}>
          {icon}
        </div>
      </div>
      {trend !== undefined && (
        <div className={`mt-3 flex items-center gap-1 text-xs font-medium ${trend >= 0 ? 'text-green-600' : 'text-red-500'}`}>
          <span>{trend >= 0 ? '↑' : '↓'}</span>
          <span>{Math.abs(trend)}% vs mois dernier</span>
        </div>
      )}
    </div>
  );
}
