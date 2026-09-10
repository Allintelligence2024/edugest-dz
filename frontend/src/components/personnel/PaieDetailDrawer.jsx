import React from 'react';
import { paieApi } from '@api/paie.api';
import toast from 'react-hot-toast';

export default function PaieDetailDrawer({ paie, onClose, onRefresh }) {
  const p = paie;
  const baseImposable = Math.max(0, (Number(p.salaire_base) || 0) - (Number(p.cnas) || 0));
  const bulletinUrl = p.bulletin_url;

  const handleTelechargerBulletin = async () => {
    try {
      const blob = await paieApi.bulletinPdf(p.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `bulletin_${p.enseignant?.nom}_${p.mois}_${p.annee}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('Erreur téléchargement bulletin');
    }
  };

  return (
    <>
      <div className="fixed inset-0 bg-black/20 z-40" onClick={onClose} />
      <div className="fixed inset-y-0 right-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
        <div className="flex items-center justify-between p-5 border-b border-neutral-100">
          <div>
            <h2 className="text-lg font-bold text-neutral-800">Détail de la paie</h2>
            <p className="text-sm text-neutral-500">
              {p.enseignant?.nom} {p.enseignant?.prenom}
            </p>
          </div>
          <button onClick={onClose} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-neutral-100 text-neutral-400">&times;</button>
        </div>

        <div className="flex-1 overflow-y-auto p-5 space-y-6">
          <div className="grid grid-cols-2 gap-4">
            <InfoRow label="Mois" value={`${p.mois}/${p.annee}`} />
            <InfoRow label="Contrat" value={p.enseignant?.type_contrat || '-'} />
            <InfoRow label="Heures" value={`${p.heures_travaillees || 0}h`} />
            <InfoRow label="Statut" value={p.statut} />
          </div>

          <div>
            <h3 className="text-sm font-semibold text-neutral-600 mb-3">Décomposition du salaire</h3>
            <div className="space-y-3">
              <DecompRow label="Salaire brut" value={p.salaire_base || 0} color="text-neutral-800" />
              <DecompRow label="CNAS (9%)" value={p.cnas || 0} color="text-red-600" minus />
              <div className="border-t border-dashed border-neutral-200 pt-2">
                <DecompRow label="Base imposable" value={baseImposable} color="text-neutral-700" />
              </div>
              <DecompRow label="IRG" value={p.irg || 0} color="text-amber-600" minus />
              <div className="border-t-2 border-neutral-300 pt-2">
                <DecompRow label="Salaire net" value={p.salaire_net || 0} color="text-green-700 font-bold" />
              </div>
            </div>
          </div>

          <div className="bg-neutral-50 rounded-xl p-4 space-y-2 text-sm">
            <p className="text-neutral-500">Barème IRG 2026 appliqué</p>
            <p className="text-neutral-500">CNAS : 9% du brut</p>
            {Number(p.irg) === 0 && (
              <p className="text-green-600 font-medium">✓ Exonéré IRG (SMIG ≤ 20 000 DA)</p>
            )}
          </div>

          <button
            onClick={handleTelechargerBulletin}
            className="w-full py-3 bg-primary-600 text-white rounded-xl font-medium hover:bg-primary-700 text-sm"
          >
            Télécharger le bulletin PDF
          </button>
        </div>
      </div>
    </>
  );
}

function InfoRow({ label, value }) {
  return (
    <div>
      <p className="text-xs text-neutral-400">{label}</p>
      <p className="text-sm font-medium text-neutral-700">{value}</p>
    </div>
  );
}

function DecompRow({ label, value, color, minus }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-neutral-600">{label}</span>
      <span className={color}>
        {minus ? '-' : ''}{Number(value).toLocaleString('fr-DZ')} DA
      </span>
    </div>
  );
}
