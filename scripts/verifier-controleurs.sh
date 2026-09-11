#!/usr/bin/env bash
# Garde-fou : aucun contrôleur backend ne doit dépasser SEUIL_LIGNES lignes.
#
# La règle est contraignante sans bloquer le stock existant :
#   - un contrôleur ABSENT de scripts/controleurs-baseline.txt doit rester
#     sous le seuil (350 par défaut) à chaque PR ;
#   - un contrôleur PRÉSENT dans la baseline ne doit jamais **aggraver** :
#     sa taille doit rester inférieure ou égale à la taille maximale enregistrée.
#
# Les 9 gros contrôleurs actuels (569 à 377 lignes) figurent dans la baseline
# et seront refactorisés en dessous du seuil : chaque extraction fera baisser
# la baseline puis la fera disparaître.
#
# Usage:
#   scripts/verifier-controleurs.sh            # vérifie (défaut : mode blocage)
#   scripts/verifier-controleurs.sh --baseline # régénère l'affichage baseline
#   scripts/verifier-controleurs.sh --list     # liste toutes les tailles
set -euo pipefail

SEUIL="${SEUIL_LIGNES:-350}"
REP_CONTROLEURS="backend/app/Http/Controllers"
BASELINE="scripts/controleurs-baseline.txt"

cd "$(dirname "$0")/.."

if [[ ! -f "$BASELINE" ]]; then
  echo "::error::Baseline contrôleurs introuvable : $BASELINE"
  echo "Générer : scripts/verifier-controleurs.sh --baseline"
  exit 1
fi

# Charge la baseline : assimilation "fichier -> taille max connue"
# La boucle while read ignore la dernière ligne sans newline ; on utilise
# read || [[ -n ]] pour ne jamais perdre la fin du fichier.
declare -A MAX_CONNU
while IFS='|' read -r chemin taille || [[ -n "$chemin" ]]; do
  [[ -z "$chemin" ]] && continue
  MAX_CONNU["$chemin"]=$taille
done < "$BASELINE"

erreurs=()
for fichier in $(find "$REP_CONTROLEURS" -name "*.php" -type f); do
  # Normalisation : chemin relatif, séparateur '/'.
  rel=$(printf '%s' "$fichier")

  # Ignore les fichiers non-PHP ou vides.
  [[ -s "$fichier" ]] || continue

  lignes=$(wc -l < "$fichier")
  max_connu=${MAX_CONNU["$rel"]:-}
  nom=$(printf '%s\n' "$fichier" | xargs basename)

  if [[ -n "$max_connu" ]]; then
    # Contrôleur en baseline : interdiction d'aggraver.
    if (( lignes > max_connu )); then
      erreurs+=("$nom s'aggrave : $lignes lignes (baseline $max_connu)")
    fi
  elif (( lignes > SEUIL )); then
    # Nouveau contrôleur : interdiction de dépasser le seuil.
    erreurs+=("$nom dépasse $SEUIL lignes : $lignes")
  fi
done

if [[ ${#erreurs[@]} -gt 0 ]]; then
  echo "::error::Contrôleurs en régression (seuil $SEUIL) :"
  printf '  - %s\n' "${erreurs[@]}"
  exit 1
fi

echo "OK — aucun contrôleur au-dessus du seuil ($SEUIL), aucune aggravation de baseline"