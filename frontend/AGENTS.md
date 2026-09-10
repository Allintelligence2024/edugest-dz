# AGENTS.md — EduGest DZ Frontend

## Contexte du projet
Application React 18 + Vite + Tailwind de gestion de cours particuliers en Algérie.
8 écrans complets avec système dark/light, KPI, tableaux, formulaires, charts CSS.

## Architecture UI
- Système de thème `data-theme` sur `<html>` (dark/light), persisté dans `localStorage` comme `edugest-theme`
- Variables CSS dans `src/styles/variables.css` : `--bg`, `--surface`, `--border`, `--text`, `--accent`, etc.
- Composants UI réutilisables dans `src/components/ui/` : Card, KpiCard, Badge, Button, Table, ProgressBar, Alert, DonutChart, BarChart
- Layout : Sidebar fixe (collapsible) + Topbar sticky + main scrollable
- Sidebar utilise des icônes SVG (pas d'émojis) mais le système de traduction `t()` conserve les clés `nav_*`
- Charts en pur CSS (flexbox + SVG), zéro bibliothèque graphique

## Règles de contribution
- Toujours utiliser Tailwind classes utilitaires après avoir importé variables.css
- Les valeurs `--*` CSS custom properties sont réservées au thème, les classes Tailwind `bg-surface`, `text-muted` etc. sont définies dans tailwind.config.js
- Ne pas utiliser de couleurs hexadécimales en dur — utiliser les classes Tailwind ou les variables CSS
- Le toggle thème doit toujours appliquer `data-theme` ET la classe `dark` (pour compat Tailwind `dark:variant`)
- Ne pas casser les tests existants (lint: `npm run lint`)
- Sauvegarder les fichiers suivants lors d'un changement de thème :
  - `tailwind.config.js` — les `colors.eg.*` correspondent aux variables CSS `--eg-*` legacy pour rétrocompatibilité
  - `src/index.css` — l'import `@import './styles/variables.css'` est obligatoire
  - `src/context/ThemeContext.jsx` — utilise `data-theme` + `class="dark"` en synchro

## Tâches restantes non faites
- Migrer les autres pages (ElevesPage, PlanningPage, FacturesPage, etc.) vers les nouveaux composants UI
- Supprimer Header.jsx legacy (remplacé par Topbar)
- Supprimer StatCard.jsx legacy (remplacé par KpiCard)
- Supprimer les `--eg-*` CSS variables de index.css si plus utilisées nulle part
- Ajouter responsive sidebar (collapsible automatique < 768px)
- Ajouter breakpoint mobile pour que la topbar masque la sidebar
- Implémenter le bouton burger menu sur mobile dans Topbar
- Vérifier que toutes les pages utilisent `bg-bg`/`text-text`/`text-muted` au lieu des styles inline hex
- Supprimer les styles inline de toasts dans App.jsx (remplacés par variables CSS)

## Commandes utiles
```bash
cd edugestdz/frontend
npm run dev       # Serveur dev
npm run build     # Build production
npm run lint      # ESLint
```
