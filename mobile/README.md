# EduGest DZ — Application Mobile

Application mobile React Native (Expo) pour parents et enseignants de la plateforme EduGest DZ.

## Stack

- **Framework** : React Native 0.76 + Expo 52
- **Navigation** : React Navigation 7 (native-stack + bottom-tabs)
- **Auth** : JWT stocké dans `expo-secure-store` avec refresh automatique
- **API** : Axios avec intercepteur JWT + refresh token rotation
- **i18n** : Français, Arabe, Darja (dz) avec support RTL
- **Cache offline** : AsyncStorage avec TTL configurable
- **Push** : Expo Notifications + FCM

## Structure

```
mobile/
  App.js                          # Point d'entrée
  app.json                        # Configuration Expo
  package.json
  src/
    api/
      axios.js                    # Client Axios avec JWT + refresh
      endpoints.js                # Appels API métier
    context/
      AuthContext.js              # Contexte d'authentification
      I18nContext.js              # Contexte i18n FR/AR/DZ
    navigation/
      AppNavigator.js             # Navigation auth stack + tabs
    screens/
      auth/LoginScreen.js         # Écran de connexion
      parent/                     # Écrans du rôle PARENT
        DashboardScreen.js
        PlanningScreen.js
        NotesScreen.js
        PresencesScreen.js
        PaiementsScreen.js
        MessagesScreen.js
        BulletinsScreen.js
        ProfileScreen.js
      enseignant/                 # Écrans du rôle ENSEIGNANT (à compléter)
        DashboardScreen.js
    services/
      cache.js                    # Service de cache offline AsyncStorage
      storage.js                  # Gestion tokens (SecureStore)
      notifications.js            # Push notifications expo-notifications
    theme/
      colors.js                   # Design system #1E5EBC
      spacing.js                  # Espacements et tailles
      index.js
```

## Rôles

### Parent
- **Dashboard** : KPIs (prochain cours, moyenne, présence, dernier paiement)
- **Planning** : Emploi du temps des enfants
- **Notes** : Relevé de notes + moyenne générale
- **Présences** : Historique des présences avec taux
- **Paiements** : Historique des paiements + total payé
- **Messages** : Messagerie avec l'établissement
- **Profil** : Langue (FR/AR/DZ), déconnexion

### Enseignant (TODO)
- Tableau de bord, planning, saisie notes, appels

## Fonctionnalités clés

- **Auth JWT** : Login, auto-refresh, SecureStore, déconnexion
- **Cache offline** : `withCache(key, fetcher, ttl)` — lit le cache, puis rafraîchit
- **i18n RTL** : Sélecteur de langue avec support RTL pour Arabe/Darja
- **Push notifications** : Enregistrement token, réception, routage par lien profond
- **Design system** : Couleurs EduGest DZ, composants réutilisables

## Démarrage

```bash
cd mobile
npm install
npx expo start
```

## Variables d'environnement

| Variable | Description |
|---|---|
| `EXPO_PUBLIC_API_URL` | URL de l'API backend (défaut: `http://localhost:8000/api/v1`) |

## Prochaines étapes

- [ ] Écrans Enseignant complets
- [ ] Élèves multiples pour un parent
- [ ] Paiement en ligne via WebView Satim
- [ ] QR code présence
- [ ] Notifications push réelles (FCM)
- [ ] Tests unitaires (Jest + React Native Testing Library)
