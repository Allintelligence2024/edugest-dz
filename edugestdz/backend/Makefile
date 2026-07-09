.PHONY: help install migrate test lint clean backup deploy health

help:
	@echo "EduGest DZ — Commandes de déploiement"
	@echo "  make install    → Installer les dépendances"
	@echo "  make migrate   → Exécuter les migrations"
	@echo "  make seed      → Seed la base"
	@echo "  make test      → Lancer les tests"
	@echo "  make lint      → Analyser le code"
	@echo "  make optimize  → Optimiser le cache"
	@echo "  make backup    → Sauvegarder la base"
	@echo "  make smoke     → Smoke test API"
	@echo "  make deploy    → Déploiement complet"

install:
	composer install --no-dev --optimize-autoloader --no-interaction --no-progress

migrate:
	php artisan migrate --force

seed:
	php artisan db:seed --class=InitialProductionSeeder --force

test:
	php artisan test --parallel

lint:
	vendor/bin/phpstan analyse --memory-limit=2G || true
	vendor/bin/pint --test -v || true

optimize:
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache

backup:
	@sh scripts/backup.sh

smoke:
	@sh scripts/smoke-test.sh

deploy: install optimize migrate seed
	@echo "Déploiement terminé"
