#!/usr/bin/env bash
#
# Analyse statique du backend — PHPStan niveau 6 + Larastan.
#
# Larastan n'est volontairement PAS déclaré dans composer.json : l'ajouter
# invaliderait composer.lock, or l'environnement de travail ne dispose pas de
# PHP pour régénérer le lock. Le paquet est donc installé à la volée, ici et
# en CI. Quand un poste disposant de PHP reprend la main, la bonne suite est :
#
#   composer require --dev larastan/larastan:^3.0
#   composer analyse -- --generate-baseline
#
# … puis rendre l'étape CI bloquante.

set -euo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="${RACINE}/edugestdz/backend"

cd "${BACKEND}"

if ! command -v php >/dev/null 2>&1; then
  echo "✖ PHP est introuvable. L'analyse statique nécessite PHP 8.2+."
  exit 127
fi

if [ ! -f vendor/autoload.php ]; then
  echo "→ Installation des dépendances Composer…"
  composer install --no-progress --no-interaction --prefer-dist
fi

if [ ! -f vendor/bin/phpstan ]; then
  echo "→ Installation de Larastan (non déclaré dans composer.json, voir en-tête)…"
  # Les advisories PKSA sur laravel/framework (verrouillé en 11.31) font
  # échouer le résolveur Composer alors que composer install passe. On
  # désactive ce blocage en config globale (jamais dans composer.json) : le
  # job CI "qualité" porte un composer audit EXPRÈS, c'est lui qui alerte.
  composer config --global policy.advisories.block false 2>/dev/null || true
  composer require --dev --no-progress --no-interaction --with-all-dependencies \
    "larastan/larastan:^3.0"
fi

# Larastan s'ajoute par un `includes:` ; on le compose ici pour que
# phpstan.neon reste valide même sans la dépendance installée.
# Le fichier temporaire est créé DANS le backend (pas /tmp) : phpstan.neon
# contient des `paths:` relatifs résolus depuis le répertoire de la config.
# Le placer en /tmp résoudrait app/config/database/routes en /tmp/… → introuvables.
CONFIG="$(mktemp "${BACKEND}/phpstan-XXXXXX.neon")"
trap 'rm -f "${CONFIG}"' EXIT

EXTENSION="${BACKEND}/vendor/larastan/larastan/extension.neon"
BASELINE="${BACKEND}/phpstan-baseline.neon"

{
  if [ -f "${EXTENSION}" ] || [ -f "${BASELINE}" ]; then
    echo "includes:"
    if [ -f "${EXTENSION}" ]; then
      echo "    - ${EXTENSION}"
    fi
    if [ -f "${BASELINE}" ]; then
      echo "    - ${BASELINE}"
    fi
    echo
  fi
  cat phpstan.neon
} > "${CONFIG}"

echo "→ PHPStan niveau 6 (configuration : phpstan.neon)"
exec php -d memory_limit=1G vendor/bin/phpstan analyse \
  --configuration="${CONFIG}" \
  --no-progress \
  --error-format=github \
  "$@"
