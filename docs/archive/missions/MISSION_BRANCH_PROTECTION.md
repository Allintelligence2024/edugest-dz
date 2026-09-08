# 🔒 MISSION DEEPSEEK — Branch Protection + Nettoyage Git
## EduGest DZ · 30 Juin 2026
## Durée estimée : 15 minutes · Aucun code à écrire

---

## CONTEXTE EXACT

### État actuel du repo (vérifié)
- **main** : CI vert (run #24) · Dernière mise à jour 13h ago · **AUCUNE protection**
- **develop** : Run #25 en cours (Mission C Paie+Billets) · 24 min ago
- **hotfix/composer-php82** : Mergée dans main via PR #3 · **À supprimer**
- **Pull requests ouvertes** : develop → main (Mission C à merger)

### Pourquoi c'est critique
Sans branch protection sur `main` :
- N'importe qui peut faire `git push origin main` directement
- Un push accidentel peut écraser l'historique ou introduire du code non testé
- Le CI peut être court-circuité

---

## ÉTAPE 1 — Merger la PR de develop → main (Mission C)

### 1a. Vérifier que le CI #25 est vert sur develop
```
https://github.com/Allintelligence2024/edugest-dz/actions
```
→ Attendre que le run #25 soit terminé et vert.
→ Si rouge : corriger d'abord avant de continuer.

### 1b. Ouvrir ou vérifier la PR
```
https://github.com/Allintelligence2024/edugest-dz/compare/main...develop
```

Si aucune PR ouverte, en créer une :
- **Titre :** `feat: Paie personnel (IRG/CNAS) + Billets entrée/retard/sortie + 15 tests`
- **Base :** `main`
- **Compare :** `develop`

### 1c. Merger la PR
→ Cliquer **"Merge pull request"** → **"Create a merge commit"** → **"Confirm merge"**

### 1d. Vérifier que le CI sur main passe au vert après le merge
```
https://github.com/Allintelligence2024/edugest-dz/actions
```
→ Attendre le run sur `main` · Confirmer vert avant de continuer.

---

## ÉTAPE 2 — Configurer la Branch Protection sur main

**DeepSeek ne peut pas faire ça directement — c'est une action GitHub UI.**

### Donner ces instructions exactes au propriétaire du repo (toi) :

```
1. Aller sur : https://github.com/Allintelligence2024/edugest-dz/settings/branches

2. Cliquer : "Add branch ruleset" (ou "Add rule" selon l'interface)

3. Remplir :
   - Ruleset name : "Protect main"
   - Enforcement status : Active
   - Target branches : Add target → "main"

4. Cocher ces règles :
   ✅ Require a pull request before merging
      - Required approvals : 1 (mettre 0 si solo — tu es seul)
      - ✅ Dismiss stale pull request approvals when new commits are pushed
   
   ✅ Require status checks to pass before merging
      - Cliquer "Add checks" → taper "backend" → sélectionner "CI — EduGest DZ / backend"
      - ✅ Require branches to be up to date before merging
   
   ✅ Require conversation resolution before merging
   
   ✅ Block force pushes
   
   ✅ Restrict deletions

5. Cliquer "Create" (ou "Save changes")
```

### ⚠️ Si tu es solo founder (pas d'équipe)
Mettre **Required approvals = 0** sinon tu seras bloqué pour merger tes propres PRs.
La protection reste utile : elle force le CI à passer avant tout merge.

---

## ÉTAPE 3 — Vérifier que la protection fonctionne

Après avoir configuré la protection, tester :

```bash
# Depuis develop, tenter un push direct sur main
git checkout main
git commit --allow-empty -m "test protection"
git push origin main
```

→ **Attendu :** GitHub doit rejeter avec :
```
remote: error: GH006: Protected branch update failed for refs/heads/main.
remote: error: - Required status check "CI — EduGest DZ / backend" is expected.
```

→ Si c'est le cas : la protection fonctionne. ✅

Annuler le commit de test :
```bash
git reset HEAD~1
```

---

## ÉTAPE 4 — Supprimer la branche hotfix/composer-php82

Cette branche est mergée depuis PR #3. Elle ne sert plus à rien.

```bash
# Supprimer localement
git branch -d hotfix/composer-php82

# Supprimer sur GitHub
git push origin --delete hotfix/composer-php82
```

Ou via GitHub UI :
```
https://github.com/Allintelligence2024/edugest-dz/branches
→ Cliquer l'icône 🗑️ à droite de "hotfix/composer-php82"
```

---

## ÉTAPE 5 — Resynchroniser develop avec main

Après le merge de la Mission C dans main :

```bash
git checkout develop
git pull origin main
git push origin develop
```

→ develop et main doivent être au même niveau.

---

## ÉTAPE 6 — Vérification finale complète

```bash
# 1. Vérifier l'état des branches
git branch -a
git log --oneline -5

# 2. Vérifier que les tests passent toujours
git checkout develop
php artisan test --parallel
# → 275+ tests verts attendus

# 3. Vérifier que main est protégé
# → Sur GitHub Settings → Branches → voir la règle active sur main

# 4. Tenter un push direct sur main pour confirmer le rejet
git checkout main
git commit --allow-empty -m "test"
git push origin main
# → Doit être rejeté par GitHub

# 5. Annuler le commit de test
git reset HEAD~1
git checkout develop
```

---

## RÉSUMÉ DES ACTIONS

| Étape | Action | Qui | Durée |
|---|---|---|---|
| 1 | Merger PR develop → main (Mission C) | DeepSeek | 2 min |
| 2 | Configurer branch protection main | **TOI sur GitHub** | 3 min |
| 3 | Vérifier rejet push direct | DeepSeek | 1 min |
| 4 | Supprimer branche hotfix/composer-php82 | DeepSeek | 1 min |
| 5 | Resynchroniser develop avec main | DeepSeek | 1 min |
| 6 | Vérification finale 275+ tests | DeepSeek | 5 min |

**Total : ~13 minutes**

---

## ÉTAT ATTENDU À LA FIN

```
Branches actives : main ✅ (protégée) · develop ✅ (synchronisée)
Branches supprimées : hotfix/composer-php82 ✅
PRs ouvertes : 0 ✅
CI main : ✅ vert (275+ tests)
Push direct sur main : ❌ bloqué par GitHub ✅
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git

MISSION : Branch protection + nettoyage.
Fichier : MISSION_BRANCH_PROTECTION.md

Étapes 1, 3, 4, 5, 6 → tu les exécutes.
Étape 2 → tu me donnes les instructions exactes pour que
le propriétaire du repo les fasse manuellement sur GitHub
(Settings → Branches → Add ruleset).

Confirme à la fin :
- Nombre de tests verts sur main
- Rejet confirmé du push direct sur main (message GitHub exact)
- Branche hotfix/composer-php82 supprimée
- develop synchronisé avec main
```

---

## NOTE IMPORTANTE

La branch protection (Étape 2) **ne peut pas être faite par DeepSeek via code**.
C'est une configuration GitHub qui nécessite d'être propriétaire du repo et d'agir
via l'interface web GitHub Settings.

DeepSeek peut faire toutes les autres étapes (merge PR, supprimer branche, tester, synchroniser).
Toi tu fais uniquement l'étape 2 sur GitHub.com — 3 minutes maximum.
