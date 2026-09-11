import React, { useState, useEffect, useCallback } from 'react';
import { authApi } from '@api/auth.api';
import toast from 'react-hot-toast';
import { useI18n } from '@context/I18nContext';

export default function TwoFactorSetupPage() {
  const { t } = useI18n();
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [step, setStep] = useState('idle');
  const [method, setMethod] = useState(null);
  const [secretData, setSecretData] = useState(null);
  const [code, setCode] = useState('');
  const [phone, setPhone] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  const [password, setPassword] = useState('');

  const fetchStatus = useCallback(async () => {
    try {
      const res = await authApi.get2faStatus();
      setStatus(res.data);
    } catch { /* ignore */ }
    setLoading(false);
  }, []);

  useEffect(() => { fetchStatus(); }, [fetchStatus]);

  const handleEnable = async (type) => {
    setMethod(type);
    setLoading(true);
    try {
      const res = await authApi.enable2fa(type, type === 'sms' ? phone : undefined);
      if (type === 'totp') {
        setSecretData({ secret: res.data.secret, qrCodeUrl: res.data.qr_code_url });
        setRecoveryCodes(res.data.recovery_codes);
        setStep('confirm');
      } else {
        toast.success(t('twofa_sms_envoye'));
        setStep('confirm');
      }
    } catch (err) {
      toast.error(err?.error?.message || t('twofa_erreur_activation'));
    }
    setLoading(false);
  };

  const handleConfirm = async () => {
    if (!code) {
      toast.error(t('twofa_code_requis'));
      return;
    }
    setLoading(true);
    try {
      const res = await authApi.confirm2fa(code);
      toast.success(res.message || t('twofa_activee'));
      setStep('success');
      fetchStatus();
    } catch (err) {
      toast.error(err?.error?.message || t('twofa_code_invalide'));
    }
    setLoading(false);
  };

  const handleDisable = async () => {
    if (!password) {
      toast.error(t('twofa_mot_de_passe_requis'));
      return;
    }
    setLoading(true);
    try {
      const res = await authApi.disable2fa(password);
      toast.success(res.message || t('twofa_desactivee'));
      setStep('idle');
      setSecretData(null);
      setRecoveryCodes([]);
      setCode('');
      setPassword('');
      fetchStatus();
    } catch (err) {
      toast.error(err?.error?.message || t('twofa_mot_de_passe_incorrect'));
    }
    setLoading(false);
  };

  if (loading && !status) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  const enabled = status?.enabled;

  return (
    <div className="space-y-6 max-w-2xl">
      <h1 className="text-2xl font-bold text-neutral-800">{t('twofa_titre')}</h1>

      <div className="bg-white rounded-2xl shadow-sm border border-neutral-200 p-6 space-y-6">
        {!enabled && step === 'idle' && (
          <>
            <div>
              <h2 className="text-lg font-semibold text-neutral-800 mb-2">{t('twofa_titre_section')}</h2>
              <p className="text-sm text-neutral-500">
                {t('twofa_intro')}
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <button
                onClick={() => setStep('choose')}
                className="p-5 border-2 border-neutral-200 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all text-left"
              >
                <div className="w-10 h-10 bg-primary-100 text-primary-600 rounded-lg flex items-center justify-center mb-3">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <h3 className="font-semibold text-neutral-800 mb-1">{t('twofa_methode_totp')}</h3>
                <p className="text-xs text-neutral-500">{t('twofa_totp_desc')}</p>
              </button>

              <button
                onClick={() => { setStep('choose'); setMethod('sms'); }}
                className="p-5 border-2 border-neutral-200 rounded-xl hover:border-primary-500 hover:bg-primary-50 transition-all text-left"
              >
                <div className="w-10 h-10 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mb-3">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                </div>
                <h3 className="font-semibold text-neutral-800 mb-1">SMS</h3>
                <p className="text-xs text-neutral-500">{t('twofa_sms_desc')}</p>
              </button>
            </div>
          </>
        )}

        {!enabled && step === 'choose' && (
          <>
            <button onClick={() => setStep('idle')} className="text-sm text-primary-600 hover:underline">&larr; {t('back')}</button>
            <h2 className="text-lg font-semibold text-neutral-800">
              {method === 'totp' ? t('twofa_methode_totp') : t('twofa_methode_sms')}
            </h2>

            {method === 'totp' && (
              <div className="space-y-4">
                <p className="text-sm text-neutral-500">{t('twofa_totp_scan')}</p>

                {secretData?.qrCodeUrl && (
                  <div className="flex justify-center">
                    <img src={secretData.qrCodeUrl} alt="QR Code" className="w-48 h-48 border rounded-xl" />
                  </div>
                )}

                {secretData?.secret && (
                  <div>
                    <label className="block text-sm font-semibold text-neutral-700 mb-1">{t('twofa_cle_manuelle')}</label>
                    <div className="flex gap-2">
                      <input readOnly value={secretData.secret} className="flex-1 px-4 py-2 rounded-xl border border-neutral-200 bg-neutral-50 text-sm font-mono" />
                      <button onClick={() => { navigator.clipboard.writeText(secretData.secret); toast.success(t('twofa_cle_copiee')); }} className="px-3 py-2 bg-neutral-100 rounded-xl text-sm hover:bg-neutral-200">{t('copier')}</button>
                    </div>
                  </div>
                )}
              </div>
            )}

            {method === 'sms' && (
              <div className="space-y-4">
                <p className="text-sm text-neutral-500">{t('twofa_sms_intro')}</p>
                <div>
                  <label className="block text-sm font-semibold text-neutral-700 mb-1">{t('twofa_numero_tel')}</label>
                  <input
                    type="text"
                    value={phone}
                    onChange={e => setPhone(e.target.value)}
                    placeholder="+213 5XX XX XX XX"
                    className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500"
                  />
                </div>
              </div>
            )}

            <button
              onClick={() => handleEnable(method)}
              disabled={loading}
              className="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60"
            >
              {loading ? t('twofa_envoi_en_cours') : method === 'totp' ? t('twofa_continuer') : t('twofa_envoyer_code')}
            </button>
          </>
        )}

        {!enabled && step === 'confirm' && (
          <div className="space-y-4">
            <button onClick={() => { setStep('choose'); setCode(''); }} className="text-sm text-primary-600 hover:underline">&larr; {t('back')}</button>
            <h2 className="text-lg font-semibold text-neutral-800">{t('twofa_confirmer_titre')}</h2>
            <p className="text-sm text-neutral-500">{t('twofa_confirmer_desc')}</p>

            <input
              type="text"
              value={code}
              onChange={e => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              maxLength={6}
              className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-sm text-center text-2xl tracking-[0.5em] font-mono outline-none focus:border-primary-500"
              autoFocus
            />

            <button
              onClick={handleConfirm}
              disabled={loading || code.length !== 6}
              className="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60"
            >
              {loading ? t('twofa_verification') : t('confirm')}
            </button>
          </div>
        )}

        {!enabled && step === 'success' && recoveryCodes.length > 0 && (
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
              <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <p className="text-sm font-medium">{t('twofa_codes_recup_avertissement')}</p>
            </div>

            <div className="bg-neutral-50 border border-neutral-200 rounded-xl p-4">
              <div className="grid grid-cols-2 gap-2">
                {recoveryCodes.map((rc, i) => (
                  <div key={i} className="font-mono text-sm text-neutral-700 bg-white rounded-lg px-3 py-2 border border-neutral-100">
                    {rc}
                  </div>
                ))}
              </div>
            </div>

            <button
              onClick={() => { setStep('idle'); setRecoveryCodes([]); setCode(''); }}
              className="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors"
            >
              {t('twofa_termine')}
            </button>
          </div>
        )}

        {enabled && (
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h2 className="text-lg font-semibold text-neutral-800">{t('twofa_activee_titre')}</h2>
                <p className="text-sm text-neutral-500">{t('twofa_methode')} : {status?.type === 'totp' ? t('twofa_methode_totp') : 'SMS'}</p>
              </div>
            </div>

            <hr className="border-neutral-200" />

            <div className="space-y-3">
              <h3 className="text-sm font-semibold text-neutral-700">{t('twofa_desactiver')}</h3>
              <input
                type="password"
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder={t('twofa_votre_mot_de_passe')}
                className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500"
              />
              <button
                onClick={handleDisable}
                disabled={loading || !password}
                className="w-full py-3 rounded-xl bg-red-600 text-white font-semibold text-sm hover:bg-red-700 transition-colors disabled:opacity-60"
              >
                {loading ? t('twofa_desactivation_en_cours') : t('twofa_desactiver')}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
