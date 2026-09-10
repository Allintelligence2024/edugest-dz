# Makefile
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 🚀 EDUGEST DZ — Makefile de commandes
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

.PHONY: help install up down restart logs backend frontend

# ── Couleurs ──
GREEN  = \033[0;32m
YELLOW = \033[1;33m
CYAN   = \033[0;36m
RESET  = \033[0m

help: ## 📚 Afficher l'aide
	@echo ""
	@echo "$(CYAN)╔══════════════════════════════════════════╗$(RESET)"
	@echo "$(CYAN)║    🏫 EDUGEST DZ — Makefile Commands    ║$(RESET)"
	@echo "$(CYAN)╚══════════════════════════════════════════╝$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 🚀 DÉMARRAGE & ARRÊT
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
install: ## 🔧 Installation complète du projet
	@echo "$(YELLOW)📦 Installation EduGest DZ...$(RESET)"
	@cp -n backend/.env.example backend/.env || true
	@cp -n frontend/.env.development.example frontend/.env.development || true
	@docker-compose build --no-cache
	@docker-compose up -d postgres redis
	@sleep 5
	@docker-compose run --rm app composer install
	@docker-compose run --rm app php artisan key:generate
	@docker-compose run --rm app php artisan jwt:secret
	@docker-compose run --rm app php artisan migrate:fresh --seed
	@docker-compose run --rm app php artisan storage:link
	@docker-compose run --rm frontend npm install
	@docker-compose up -d
	@echo "$(GREEN)✅ Installation terminée !$(RESET)"
	@echo "$(CYAN)🌐 App     : http://localhost$(RESET)"
	@echo "$(CYAN)⚛️  Frontend: http://localhost:5173$(RESET)"
	@echo "$(CYAN)🐘 PgAdmin : http://localhost:5050$(RESET)"
	@echo "$(CYAN)🔴 Redis UI: http://localhost:8081$(RESET)"
	@echo "$(CYAN)🔍 Search  : http://localhost:7700$(RESET)"

up: ## ▶️  Démarrer tous les services
	@echo "$(GREEN)▶️  Démarrage des services...$(RESET)"
	@docker-compose up -d
	@docker-compose ps

down: ## ⏹️  Arrêter tous les services
	@echo "$(YELLOW)⏹️  Arrêt des services...$(RESET)"
	@docker-compose down

restart: ## 🔄 Redémarrer tous les services
	@docker-compose down && docker-compose up -d

reset: ## 💥 Reset complet (DANGER : efface les données)
	@echo "$(YELLOW)⚠️  Reset complet en cours...$(RESET)"
	@docker-compose down -v
	@docker-compose up -d postgres redis
	@sleep 5
	@docker-compose run --rm app php artisan migrate:fresh --seed
	@docker-compose up -d
	@echo "$(GREEN)✅ Reset terminé$(RESET)"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 🐘 BACKEND LARAVEL
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
migrate: ## 🗄️  Lancer les migrations
	@docker-compose exec app php artisan migrate

migrate-fresh: ## 🗄️  Remettre à zéro la BDD
	@docker-compose exec app php artisan migrate:fresh --seed

make-model: ## 📝 Créer un Model (usage: make make-model name=Eleve)
	@docker-compose exec app php artisan make:model $(name) -mcr

make-controller: ## 🎮 Créer un Controller
	@docker-compose exec app php artisan make:controller $(name)

make-migration: ## 📋 Créer une Migration
	@docker-compose exec app php artisan make:migration $(name)

make-seeder: ## 🌱 Créer un Seeder
	@docker-compose exec app php artisan make:seeder $(name)

make-job: ## ⚙️  Créer un Job
	@docker-compose exec app php artisan make:job $(name)

tinker: ## 🖥️  Console Laravel interactive
	@docker-compose exec app php artisan tinker

test-backend: ## ✅ Lancer les tests PHP
	@docker-compose exec app php artisan test --coverage

clear-cache: ## 🧹 Vider tous les caches
	@docker-compose exec app php artisan config:clear
	@docker-compose exec app php artisan cache:clear
	@docker-compose exec app php artisan route:clear
	@docker-compose exec app php artisan view:clear
	@echo "$(GREEN)✅ Caches vidés$(RESET)"

composer-install: ## 📦 Installer les dépendances PHP
	@docker-compose exec app composer install

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# ⚛️  FRONTEND REACT
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
npm-install: ## 📦 Installer les dépendances Node.js
	@docker-compose exec frontend npm install

npm-build: ## 🏗️  Build production du frontend
	@docker-compose exec frontend npm run build

test-frontend: ## ✅ Lancer les tests React
	@docker-compose exec frontend npm run test

lint: ## 🔍 Vérifier le code (ESLint)
	@docker-compose exec frontend npm run lint

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 📊 MONITORING & LOGS
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
logs: ## 📋 Voir tous les logs
	@docker-compose logs -f

logs-app: ## 📋 Logs Backend uniquement
	@docker-compose logs -f app

logs-nginx: ## 📋 Logs Nginx uniquement
	@docker-compose logs -f nginx

logs-queue: ## 📋 Logs Queue Worker
	@docker-compose logs -f queue

ps: ## 📊 Statut des services
	@docker-compose ps

shell-app: ## 🐚 Shell dans le conteneur Backend
	@docker-compose exec app bash

shell-frontend: ## 🐚 Shell dans le conteneur Frontend
	@docker-compose exec frontend sh

shell-postgres: ## 🐚 Shell PostgreSQL
	@docker-compose exec postgres psql -U edugest_user -d edugestdz
