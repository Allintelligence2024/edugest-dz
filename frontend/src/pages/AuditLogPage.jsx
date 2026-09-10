import React, { useState, useEffect, useCallback } from 'react';
import { auditApi } from '@api/audit.api';
import toast from 'react-hot-toast';

export default function AuditLogPage() {
  const [logs, setLogs] = useState([]);
  const [meta, setMeta] = useState({ total: 0 });
  const [filters, setFilters] = useState({ page: 1, per_page: 30 });
  const [selectedLog, setSelectedLog] = useState(null);

  const fetchLogs = useCallback(async () => {
    try {
      const r = await auditApi.list(filters);
      setLogs(r.data || []);
      setMeta(r.meta || {});
    } catch { toast.error('Erreur chargement'); }
  }, [filters]);

  useEffect(() => { fetchLogs(); }, [fetchLogs]);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-neutral-800">Journal d'audit</h1>

      <div className="flex gap-3">
        <select onChange={e => setFilters(f => ({ ...f, action: e.target.value }))} className="px-3 py-2 border rounded-xl text-sm bg-white">
          <option value="">Toutes les actions</option>
          <option value="created">Créations</option>
          <option value="updated">Modifications</option>
          <option value="deleted">Suppressions</option>
        </select>
        <input type="date" onChange={e => setFilters(f => ({ ...f, date_debut: e.target.value }))} className="px-3 py-2 border rounded-xl text-sm bg-white" />
        <input type="date" onChange={e => setFilters(f => ({ ...f, date_fin: e.target.value }))} className="px-3 py-2 border rounded-xl text-sm bg-white" />
        <span className="text-sm text-neutral-400 self-center">{meta.total} entrées</span>
      </div>

      <div className="bg-white rounded-2xl border border-neutral-100 shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-neutral-50">
              <th className="text-left px-4 py-3 font-semibold text-neutral-600">Date</th>
              <th className="text-left px-4 py-3 font-semibold text-neutral-600">Utilisateur</th>
              <th className="text-left px-4 py-3 font-semibold text-neutral-600">Action</th>
              <th className="text-left px-4 py-3 font-semibold text-neutral-600">Table</th>
              <th className="text-center px-4 py-3 font-semibold text-neutral-600">Détail</th>
            </tr>
          </thead>
          <tbody>
            {logs.map(log => (
              <tr key={log.id} className="border-b hover:bg-neutral-50">
                <td className="px-4 py-3 text-neutral-500">{new Date(log.created_at).toLocaleDateString('fr-DZ')}</td>
                <td className="px-4 py-3">{log.causer?.nom} {log.causer?.prenom}</td>
                <td className="px-4 py-3">{log.description}</td>
                <td className="px-4 py-3 text-neutral-500">{log.subject_type?.split('\\')?.pop()}</td>
                <td className="px-4 py-3 text-center">
                  <button onClick={() => setSelectedLog(selectedLog?.id === log.id ? null : log)} className="text-primary-600 text-xs font-medium">{selectedLog?.id === log.id ? 'Masquer' : 'Voir'}</button>
                </td>
              </tr>
            ))}
            {selectedLog && (
              <tr><td colSpan={5} className="px-4 py-3 bg-neutral-50">
                <pre className="text-xs text-neutral-600 whitespace-pre-wrap max-h-64 overflow-y-auto">{JSON.stringify({ ancien: selectedLog.properties?.old, nouveau: selectedLog.properties?.attributes }, null, 2)}</pre>
              </td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
