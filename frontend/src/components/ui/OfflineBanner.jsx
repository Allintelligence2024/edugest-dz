/**
 * OfflineBanner — Bandeau discret en haut de l'écran
 * quand l'utilisateur est hors-ligne ou vient de se reconnecter.
 */

import { useOfflineStatus } from '@hooks/useOfflineStatus';
import { useI18n } from '@context/I18nContext';

import { CheckCircle, WifiOff } from 'lucide-react';

export default function OfflineBanner() {
  const { isOffline, wasOffline, pendingActions } = useOfflineStatus();
  const { t } = useI18n();

  if (!isOffline && !wasOffline) return null;

  return (
    <div
      role="status"
      aria-live="polite"
      style={{
        position:   'fixed',
        top:        0,
        left:       0,
        right:      0,
        zIndex:     9999,
        padding:    '10px 20px',
        display:    'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap:        '10px',
        fontSize:   '13px',
        fontWeight: 600,
        transition: 'all 0.3s ease',
        background: isOffline ? '#1c1917' : '#14532d',
        color:      isOffline ? '#fcd34d' : '#86efac',
        borderBottom: `1px solid ${isOffline ? '#78350f' : '#166534'}`,
      }}
    >
      {isOffline ? (
        <>
          <span><WifiOff size={16} aria-hidden='true' /></span>
          <span>{t('offline_banner_offline')}</span>
          {pendingActions > 0 && (
            <span style={{
              background: '#78350f',
              padding: '2px 8px',
              borderRadius: '12px',
              fontSize: '11px',
            }}>
              {t('offline_banner_pending', { count: pendingActions })}
            </span>
          )}
        </>
      ) : (
        <>
          <span><CheckCircle size={16} aria-hidden='true' /></span>
          <span>{t('offline_banner_back')}</span>
        </>
      )}
    </div>
  );
}
