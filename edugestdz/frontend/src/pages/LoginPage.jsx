import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { useAuth } from '@hooks/useAuth';
import { authApi } from '@api/auth.api';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [twoFactorStep, setTwoFactorStep] = useState(false);
  const [twoFactorData, setTwoFactorData] = useState(null);
  const [code, setCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) {
      toast.error('Veuillez remplir tous les champs');
      return;
    }
    setIsLoading(true);
    try {
      const res = await authApi.login(email, password);
      if (res.two_factor_required) {
        setTwoFactorData(res);
        setTwoFactorStep(true);
        return;
      }
      await login(email, password);
      toast.success('Connexion réussie !');
      navigate('/');
    } catch (err) {
      toast.error(err?.error?.message || 'Email ou mot de passe incorrect');
    } finally {
      setIsLoading(false);
    }
  };

  const handleTwoFactorSubmit = async () => {
    if (!code) {
      toast.error('Veuillez entrer le code');
      return;
    }
    setIsLoading(true);
    try {
      const res = await authApi.complete2fa(twoFactorData.temp_token, code);
      localStorage.setItem('access_token', res.access_token);
      await login(email, password);
      toast.success('Connexion réussie !');
      navigate('/');
    } catch (err) {
      toast.error(err?.error?.message || 'Code 2FA invalide');
    } finally {
      setIsLoading(false);
    }
  };

  if (twoFactorStep) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-primary-500 to-primary-800 flex items-center justify-center p-4">
        <div className="bg-white rounded-3xl shadow-modal w-full max-w-md p-8 animate-slide-up">
          <div className="text-center mb-8">
            <div className="w-14 h-14 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h1 className="text-2xl font-bold text-neutral-800">Vérification en deux étapes</h1>
            <p className="text-neutral-500 mt-2 text-sm">
              {useRecovery
                ? 'Saisissez un code de récupération'
                : 'Saisissez le code à 6 chiffres de votre application d\'authentification'}
            </p>
          </div>

          <div className="space-y-5">
            <input
              type="text"
              value={code}
              onChange={e => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder={useRecovery ? 'Code de récupération' : '000000'}
              maxLength={6}
              className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-center text-2xl tracking-[0.5em] font-mono outline-none focus:border-primary-500 transition-colors"
              autoFocus
            />

            <button
              onClick={handleTwoFactorSubmit}
              disabled={isLoading || !code}
              className="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60"
            >
              {isLoading ? 'Vérification...' : 'Vérifier'}
            </button>

            <button
              onClick={() => { setUseRecovery(!useRecovery); setCode(''); }}
              className="w-full text-sm text-primary-600 hover:underline text-center block"
            >
              {useRecovery ? 'Utiliser l\'application d\'authentification' : 'Utiliser un code de récupération'}
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-500 to-primary-800 flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl shadow-modal w-full max-w-md p-8 animate-slide-up">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-neutral-800">EduGest DZ</h1>
          <p className="text-neutral-500 mt-2">Gestion des cours particuliers</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">Email</label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="votre@email.com"
              className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 transition-colors"
              autoFocus
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-neutral-700 mb-1.5">Mot de passe</label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full px-4 py-3 rounded-xl border-2 border-neutral-200 text-sm outline-none focus:border-primary-500 transition-colors"
            />
          </div>
          <button
            type="submit"
            disabled={isLoading}
            className="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
          >
            {isLoading ? <><span className="animate-spin">⏳</span> Connexion...</> : 'Se connecter'}
          </button>
        </form>
        <p className="text-center text-xs text-neutral-400 mt-6">
          &copy; {new Date().getFullYear()} EduGest DZ — Tous droits réservés
        </p>
      </div>
    </div>
  );
}
