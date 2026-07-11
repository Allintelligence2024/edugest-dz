import React, { useState, useEffect, useCallback } from 'react';
import api from '@api/axiosInstance';
import toast from 'react-hot-toast';

const TYPE_ICONS = {
  info: { icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#2563EB' },
  succes: { icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: '#10B981' },
  alerte: { icon: 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#F59E0B' },
  erreur: { icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#EF4444' },
};

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [endpointError, setEndpointError] = useState(false);

  const fetchNotifications = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get('/notifications', { params: { per_page: 50 } });
      setNotifications(res.data || []);
    } catch {
      setEndpointError(true);
      setNotifications([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchNotifications(); }, [fetchNotifications]);

  const markAsRead = async (id) => {
    try {
      await api.put(`/notifications/${id}/lu`);
      setNotifications(prev =>
        prev.map(n => n.id === id ? { ...n, lu: true } : n)
      );
    } catch { toast.error('Erreur lors de la mise à jour'); }
  };

  const markAllAsRead = async () => {
    try {
      await api.put('/notifications/lu');
      setNotifications(prev => prev.map(n => ({ ...n, lu: true })));
      toast.success('Toutes les notifications marquées comme lues');
    } catch { toast.error('Erreur lors de la mise à jour'); }
  };

  const unreadCount = notifications.filter(n => !n.lu).length;

  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return "À l'instant";
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `Il y a ${diffH}h`;
    return d.toLocaleDateString('fr-DZ', { day: '2-digit', month: 'short' });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-3 border-border rounded-full animate-spin" style={{ borderTopColor: 'var(--accent)' }} />
      </div>
    );
  }

  if (endpointError) {
    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold text-text">Notifications</h1>
        </div>
        <div className="bg-surface rounded-2xl border border-border p-12 text-center">
          <div className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style={{ background: 'var(--accent)18' }}>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" strokeWidth="1.5">
              <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
          <p className="text-muted text-sm">Aucune notification pour le moment.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text">Notifications</h1>
          {unreadCount > 0 && (
            <p className="text-muted text-sm mt-1">{unreadCount} non lue{unreadCount > 1 ? 's' : ''}</p>
          )}
        </div>
        {unreadCount > 0 && (
          <button
            onClick={markAllAsRead}
            className="px-4 py-2 text-sm font-medium rounded-xl transition-colors"
            style={{ color: 'var(--accent)', background: 'var(--accent)12' }}
          >
            Tout marquer comme lu
          </button>
        )}
      </div>

      {notifications.length === 0 ? (
        <div className="bg-surface rounded-2xl border border-border p-12 text-center">
          <div className="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style={{ background: 'var(--green)18' }}>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--green)" strokeWidth="1.5">
              <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p className="text-muted text-sm">Tout est à jour ! Aucune notification.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {notifications.map(notif => {
            const typeConfig = TYPE_ICONS[notif.type] || TYPE_ICONS.info;
            return (
              <div
                key={notif.id}
                onClick={() => !notif.lu && markAsRead(notif.id)}
                className="flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all duration-150"
                style={{
                  background: notif.lu ? 'var(--surface)' : 'var(--surface2)',
                  borderColor: notif.lu ? 'var(--border)' : 'var(--accent)40',
                }}
              >
                <div
                  className="w-10 h-10 rounded-xl shrink-0 flex items-center justify-center mt-0.5"
                  style={{ background: `${typeConfig.color}18` }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={typeConfig.color} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <path d={typeConfig.icon} />
                  </svg>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="text-sm font-semibold text-text truncate">{notif.titre || 'Notification'}</p>
                    {!notif.lu && <span className="w-2 h-2 rounded-full shrink-0" style={{ background: 'var(--accent)' }} />}
                  </div>
                  <p className="text-xs text-muted mt-0.5 line-clamp-2">{notif.message || notif.corps || ''}</p>
                  <p className="text-[10px] text-muted2 mt-1">{formatDate(notif.created_at)}</p>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
