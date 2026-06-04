# Rapport d'Analyse — Projet "Upload de Travaux"

**Date :** 04/06/2026
**Technologies :** Vue.js 2, PHP 8, JSZip, Bootstrap 5, CSS3
**Score de santé :** 9 / 10
**Risque sécurité :** FAIBLE

---

## 1. Correctifs déjà appliqués (Terminés)

### Sécurité
- Blocage `..` dans `sanitizePathComponent()`
- `.htaccess` avec `php_flag engine off` dans `upload_folder/`
- Validation MIME + ouverture réelle ZIP avec ZipArchive
- Token CSRF (génération + validation)

### Robustesse
- Rate limiting IP-based (1 upload / 30s)
- Journalisation des uploads dans `upload.log`
- Gestion d'erreurs JS (`fetchClasses`, `getElementById`)
- Correction fuite mémoire drag & drop

### UX/UI
- Transitions entre steps
- Classe "Autre" fonctionnelle
- Indicateur "Étape X/4" dans le stepper

### Code
- `var` → `let`
- Logique `onClasseChanged` clarifiée
- `jszip.js` supprimé (non minifié)
- `declare(strict_types=1)` en PHP

### Administration
- Page admin avec API REST + Vue.js SPA
- 3 onglets : Arborescence, Statistiques, Journal
- Suppression/Téléchargement par sélection
- Export CSV, thème sombre, rafraîchissement auto

---

## 2. Améliorations possibles

### 🔒 Sécurité

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| 1 | **Vérifier config PHP au démarrage** | `upload.php` | Vérifier que `upload_max_filesize` et `post_max_size` sont ≥ 100MB au début du script, renvoyer une erreur claire si insuffisant |
| 2 | **Limiter le nombre de fichiers dans le ZIP** | `app.js` | Vérifier côté serveur aussi (actuellement seulement côté client) |
| 3 | **Validation du nom de fichier uploadé** | `upload.php` | Vérifier que `$_FILES['files']['name']` ne contient pas de caractères dangereux avant d'utiliser `basename()` |

### 🛡️ Robustesse

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| 4 | **Gérer le cas où le dossier `upload_folder/` n'existe pas** | `admin.php`, `api_admin.php` | Ajouter une vérification avec message d'erreur clair |
| 5 | **Vérifier que PHP peut écrire dans `upload_folder/`** | `upload.php` | Tester les permissions d'écriture avant l'upload |
| 6 | **Limiter la durée de vie du fichier de rate limiting** | `upload.php` | Nettoyer les fichiers temporaires de rate limiting après expiration |
| 7 | **Meilleure gestion des erreurs réseau dans l'admin** | `admin.js` | Afficher une page d'erreur si l'API est inaccessible |

### ✨ UX/UI (Application principale)

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| 8 | **Barre de progression upload** | `app.js` | Remplacer `fetch()` par `XMLHttpRequest` avec `xhr.upload.onprogress` pour afficher le % en temps réel |
| 9 | **Aperçu du code saisi** | `index.php`, `app.js` | Afficher un extrait tronqué du code dans la prévisualisation |
| 10 | **Bouton "Envoyer" sticky sur mobile** | `style.css` | Ajouter un `position: sticky` ou scroll automatique vers le bouton |
| 11 | **Navigation clavier radio-cards** | `index.php`, `app.js` | Ajouter `tabindex` et gestion des flèches pour les cartes de sélection |
| 12 | **Message d'erreur si JSZip non chargé** | `app.js` | Vérifier que `JSZip` est défini avant de l'utiliser |
| 13 | **Feedback sonore** | `app.js` | Jouer un son court en cas de succès/erreur |

### 🧹 Code & Architecture

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| 14 | **Configuration externalisée** | Nouveau fichier `config.json` | Centraliser les limites (100MB, 1000 fichiers, 100000 caractères) dans un fichier de config partagé JS/PHP |
| 15 | **Séparation du code PHP en classes** | `upload.php`, `api_admin.php` | Extraire les helpers dans un fichier `includes/` commun |
| 16 | **Versionner les assets** | `index.php`, `admin.php` | Ajouter un timestamp aux URLs des fichiers CSS/JS pour éviter le cache navigateur après mise à jour |
| 17 | **Supprimer les fichiers inutilisés** | Divers | Vérifier si `bootstrap.min.js`, `bootstrap.min.js.map` et les images dans `assets/images/` sont nécessaires |

### 🚀 Évolutions

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| 18 | **Page d'administration avancée** | `admin.php` | Ajouter la vue par classe (filtrage), afficher les métadonnées IP, permettre le téléchargement par date |
| 19 | **Notification email** | Nouveau fichier | Envoyer un email au professeur à chaque upload |
| 20 | **Support multi-langue** | `index.php`, `app.js`, `upload.php` | Ajouter un système i18n simple (français/anglais) |
| 21 | **Mode hors-ligne (PWA)** | Nouveaux fichiers | Service worker + manifest pour utiliser l'app sans connexion |
| 22 | **Scan antivirus** | `upload.php` | Intégrer ClamAV pour scanner les ZIP avant stockage |
| 23 | **Barre de progression upload** | `app.js` | Remplacer le spinner par une vraie barre de progression |

---

## 3. Résumé par priorité

| Priorité | # | Amélioration | Effort |
|----------|---|-------------|--------|
| Haute | 1 | Vérifier config PHP | 15 min |
| Haute | 2 | Validation côté serveur (nb fichiers) | 15 min |
| Haute | 8 | Barre de progression upload | 2h |
| Moyenne | 4 | Dossier upload_folder manquant | 10 min |
| Moyenne | 5 | Permissions d'écriture | 10 min |
| Moyenne | 14 | Configuration externalisée | 1h |
| Moyenne | 9 | Aperçu du code | 30 min |
| Basse | 10-13 | Améliorations UX mineures | 30 min |
| Basse | 15 | Refactoring PHP | 2h |
| Basse | 16 | Cache busting assets | 15 min |
| Basse | 17 | Nettoyage fichiers inutilisés | 15 min |
| Optionnel | 18-23 | Évolutions | Variable |

---

*Rapport généré le 04/06/2026.*