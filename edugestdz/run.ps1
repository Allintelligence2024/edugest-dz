# run.ps1
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 🚀 EDUGEST DZ — PowerShell Command Runner
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

param (
    [Parameter(Mandatory=$true, Position=0)]
    [ValidateSet("help", "install", "up", "down", "restart", "migrate", "migrate-fresh", "seed", "tinker", "clear-cache", "logs", "ps", "shell-app")]
    [string]$Action
)

function Show-Help {
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║    🏫 EDUGEST DZ — Windows Runner        ║" -ForegroundColor Cyan
    Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage: .\run.ps1 <action>" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Actions:"
    Write-Host "  install         🔧 Full installation & bootstrap of the project"
    Write-Host "  up              ▶️  Start all Docker services"
    Write-Host "  down            ⏹️  Stop and tear down all services"
    Write-Host "  restart         🔄 Restart all services"
    Write-Host "  migrate         🗄️  Run database migrations"
    Write-Host "  migrate-fresh   🗄️  Fresh database reset and seed"
    Write-Host "  seed            🌱 Populate database seeds"
    Write-Host "  tinker          🖥️  Interactive Laravel console"
    Write-Host "  clear-cache     🧹 Clear all application caches"
    Write-Host "  logs            📋 View real-time logs"
    Write-Host "  ps              📊 Show running services status"
    Write-Host "  shell-app       🐚 Access backend bash console"
    Write-Host ""
}

switch ($Action) {
    "help" {
        Show-Help
    }
    "install" {
        Write-Host "📦 Starting full EduGest DZ installation..." -ForegroundColor Yellow
        
        if (-not (Test-Path "backend\.env")) {
            Copy-Item "backend\.env.example" "backend\.env"
            Write-Host "✅ Created backend/.env" -ForegroundColor Green
        }
        if (-not (Test-Path "frontend\.env.development")) {
            Copy-Item "frontend\.env.development.example" "frontend\.env.development" -ErrorAction SilentlyContinue
            Write-Host "✅ Created frontend/.env.development" -ForegroundColor Green
        }

        Write-Host "Building Docker images..." -ForegroundColor Yellow
        docker-compose build --no-cache

        Write-Host "Booting database and cache stores..." -ForegroundColor Yellow
        docker-compose up -d postgres redis
        
        Write-Host "Waiting 5 seconds for services to settle..." -ForegroundColor Yellow
        Start-Sleep -Seconds 5

        Write-Host "Installing backend Composer packages..." -ForegroundColor Yellow
        docker-compose run --rm app composer install

        Write-Host "Generating application encryption keys..." -ForegroundColor Yellow
        docker-compose run --rm app php artisan key:generate
        docker-compose run --rm app php artisan jwt:secret

        Write-Host "Running database migrations and seeds..." -ForegroundColor Yellow
        docker-compose run --rm app php artisan migrate:fresh --seed

        Write-Host "Creating file storage symlink..." -ForegroundColor Yellow
        docker-compose run --rm app php artisan storage:link

        Write-Host "Booting up the remaining services..." -ForegroundColor Yellow
        docker-compose up -d

        Write-Host ""
        Write-Host "✅ Installation completed successfully!" -ForegroundColor Green
        Write-Host "🌐 React Frontend  : http://localhost" -ForegroundColor Cyan
        Write-Host "🐘 PgAdmin console  : http://localhost:5050" -ForegroundColor Cyan
        Write-Host "🔴 Redis UI Console : http://localhost:8081" -ForegroundColor Cyan
        Write-Host "🔍 Search Engine    : http://localhost:7700" -ForegroundColor Cyan
    }
    "up" {
        Write-Host "▶️ Starting services..." -ForegroundColor Green
        docker-compose up -d
        docker-compose ps
    }
    "down" {
        Write-Host "⏹️ Stopping services..." -ForegroundColor Yellow
        docker-compose down
    }
    "restart" {
        Write-Host "🔄 Restarting services..." -ForegroundColor Yellow
        docker-compose down
        docker-compose up -d
    }
    "migrate" {
        docker-compose exec app php artisan migrate
    }
    "migrate-fresh" {
        docker-compose exec app php artisan migrate:fresh --seed
    }
    "seed" {
        docker-compose exec app php artisan db:seed
    }
    "tinker" {
        docker-compose exec app php artisan tinker
    }
    "clear-cache" {
        docker-compose exec app php artisan config:clear
        docker-compose exec app php artisan cache:clear
        docker-compose exec app php artisan route:clear
        docker-compose exec app php artisan view:clear
        Write-Host "✅ All caches cleared!" -ForegroundColor Green
    }
    "logs" {
        docker-compose logs -f
    }
    "ps" {
        docker-compose ps
    }
    "shell-app" {
        docker-compose exec app bash
    }
}
