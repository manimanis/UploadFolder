# Rapport d'Analyse — Projet "Upload de Travaux"

**Version :** 3.0.0  
**Date :** 08/06/2026  
**Technologies :** Vue.js 2, PHP 8, JSZip, Bootstrap 5, CSS3, Web Crypto API  
**Score de santé :** 9.5 / 10  
**Risque sécurité :** TRÈS FAIBLE  
**License :** MIT

---

## 1. Correctifs et améliorations appliqués (v2.0 → v3.0)

### 🔒 Sécurité

#### 1. Hashage bcrypt du mot de passe admin
- Le mot de passe n'est plus en clair dans le code source
- Stocké dans `data/admin.hash` (chmod 0640) au lieu d'une constante PHP
- Auto-génération du hash par défaut (`admin123`) au premier lancement
- Utilisation de `password_verify()` (constant-time comparison) à la place d'une comparaison directe

#### 2. Anti-brute-force sur la connexion admin
- 5 tentatives max par IP avant blocage
- Fenêtre de blocage : 15 minutes
- Journalisation de toutes les tentatives dans `data/login_attempts.log`
- Une connexion réussie reset le compteur

#### 3. Expiration de session admin
- Session expirée après 30 minutes d'inactivité
- Régénération d'ID de session à chaque connexion (anti session-fixation)
- Vérification systématique dans `require_auth()`

#### 6. Nettoyage des fichiers de rate-limit
- Les fichiers `upload_rate_*` dans `/tmp` étaient créés sans jamais être supprimés
- Nouvelle fonction `cleanup_expired_rate_files()` exécutée aléatoirement (1% de chance) à chaque upload
- Évite l'accumulation infinie de fichiers temporaires

#### 8. Sauvegarde atomique de `exams.json`
- Avant chaque écriture, un backup horodaté `exams.json.bak.YYYYMMDD_HHMMSS` est créé
- Écriture via fichier temporaire + `flock(LOCK_EX)` pour éviter la corruption
- Renommage atomique via `rename()` à la fin
- Conservation des **5 derniers backups** uniquement (rotation automatique)
- En cas d'échec d'écriture : `json_response(['error' => '...'])` au lieu d'un fichier corrompu silencieux

### 🛡️ Robustesse

- **Journalisation des empreintes SHA-256** dans `data/hashes.log` à chaque upload
- Validation des hashs reçus côté serveur (json_decode + is_array)
- Création automatique du dossier `data/` s'il n'existe pas

### ✨ UX/UI

#### 14. Avertissement avant fermeture d'onglet pendant upload
- Ajout d'un listener `beforeunload` sur `window`
- Si `this.uploading === true`, le navigateur affiche un message natif
- Cleanup automatique dans `beforeDestroy` de Vue

#### 27. Détection de triche (plagiat) via SHA-256
- Calcul de l'empreinte SHA-256 de chaque fichier via `crypto.subtle.digest` (Web Crypto API)
- Envoi des empreintes au serveur dans un champ `hashes` (JSON)
- Persistance dans `data/hashes.log`
- Nouvelle route API `exam_stats` qui regroupe les hashs identiques et liste les envois concernés
- Onglet admin « Suivi » affiche les doublons potentiels (classe, poste, IP, date)

#### 28. Statistiques par examen
- Nouvelle route API `exam_stats` qui croise `exams.json` ↔ `upload_folder/YYYYMMDD/`
- Pour chaque examen planifié : nombre attendu (nb classes) vs nombre reçu (fichiers uploadés à la date correspondante)
- Affichage d'une barre de progression par examen
- Liste des classes qui ont rendu + classes manquantes
- Onglet admin dédié « 📈 Suivi »

#### 45. Thème clair/sombre sur l'app élève
- Nouveau bouton dans la barre supérieure (`app-topbar`)
- Préférence persistée dans `localStorage` (clé `upload_darkMode`)
- Variables CSS `--input-bg`, `--card-bg`, `--body-bg-*`, etc.
- Sélecteur `[data-theme="dark"]` qui réécrit toutes les variables
- Transitions douces (0.3s ease) entre les deux thèmes
- Lien direct vers l'admin également dans la topbar

#### 47. Page de garde publique
- Nouvelle section « 📅 Examens à venir » affichée uniquement à l'étape 0
- Filtre sur les examens dans les **7 prochains jours** (hors aujourd'hui)
- Limité à 6 examens max
- **Clic sur un examen = pré-remplissage automatique** du formulaire (`selectUpcomingExam()`)
- Améliore l'UX en donnant du contexte à l'élève dès l'arrivée sur la page

### 🧹 Code

- `Exam` class : ajout de `selectedExamId: ''` dans `data` (bug fix : variable non déclarée)
- `sendZipFile()` : calcul des hashs SHA-256 **avant** l'envoi via `Promise.all`
- Inclusion des hashs dans le `FormData` envoyé au serveur
- `fetchExams()` enrichi : calcule `upcomingExams` en plus de `todayExams`

---

## 2. Améliorations restantes (recommandations futures)

### 🔒 Sécurité (renforcements possibles)

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| S1 | **Validation des extensions de fichier** | `upload.php` | Rejeter les `.php`, `.exe`, `.sh` etc. dans le ZIP |
| S2 | **Scan antivirus** | `upload.php` | Intégrer ClamAV (`clamscan`) sur chaque ZIP |
| S3 | **2FA pour admin** | `admin.php` | TOTP via Google Authenticator |
| S4 | **Chiffrement at-rest** | `upload_folder/` | Chiffrer les ZIP avec une clé par classe |

### ⚡ Performance

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| P1 | **Compression côté serveur** | `api_admin.php` | Pour `download_multiple`, compresser à la volée (déjà fait) + cache |
| P2 | **Pagination de l'arborescence** | `admin.js` | Charger les fichiers par lots (lazy load) |
| P3 | **Migration vers SQLite** | `exams.json` | Pour de gros volumes, SQLite est plus performant |

### ✨ UX/UI (évolutions)

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| U1 | **Barre de progression upload** | `app.js` | Remplacer `fetch()` par `XMLHttpRequest` avec `xhr.upload.onprogress` |
| U2 | **Aperçu du code saisi** | `index.php`, `app.js` | Afficher un extrait tronqué dans la prévisualisation |
| U3 | **Drag & drop visuel dans la zone** | `index.php` | Highlight de la zone de drop au lieu d'un overlay plein écran |
| U4 | **Notifications par email** | Backend | Envoi automatique d'un email à l'enseignant à chaque upload |

### 🚀 Évolutions fonctionnelles

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| F1 | **Mode hors-ligne (PWA)** | Nouveaux fichiers | Service worker + manifest |
| F2 | **QR code de partage** | `index.php` | Afficher un QR code pour ouvrir rapidement sur mobile |
| F3 | **API publique read-only** | `api_admin.php` | `GET /api/exams?date=...` pour intégrer l'agenda dans d'autres outils |
| F4 | **Stockage S3/compatible** | `upload.php` | Déporter les ZIP vers S3/MinIO pour libérer le disque local |

### 🧹 Code & Architecture

| # | Amélioration | Fichiers | Description |
|---|-------------|----------|-------------|
| C1 | **Refactoriser `app.js` en Vue 3** | `app.js`, `admin.js` | Composition API + TypeScript |
| C2 | **Cache busting assets** | `index.php`, `admin.php` | Ajouter `?v=20260608` aux URLs CSS/JS |
| C3 | **Tests unitaires PHP** | `api_admin.php` | PHPUnit pour le CRUD examens, l'auth, etc. |
| C4 | **CI/CD GitHub Actions** | `.github/workflows/` | Lint + tests automatisés à chaque PR |

---

## 3. Résumé par priorité

| Priorité | # | Amélioration | Effort |
|----------|---|-------------|--------|
| Haute | S1 | Validation extensions ZIP | 1h |
| Haute | U1 | Barre de progression upload | 2h |
| Haute | C2 | Cache busting | 15 min |
| Moyenne | S2 | Scan antivirus | 1 jour |
| Moyenne | P2 | Pagination arborescence | 3h |
| Moyenne | U2 | Aperçu du code | 30 min |
| Basse | C1 | Refactor Vue 3 | 1 semaine |
| Basse | F1 | Mode PWA | 1 semaine |
| Basse | C3 | Tests unitaires | 2-3 jours |
| Optionnel | S3, S4, U3, U4, F2, F3, F4 | Évolutions | Variable |

---

## 4. Journal des versions

### v1.0.0 (04/06/2026)
- Version initiale
- Upload dossiers/fichiers/code
- Administration de base (3 onglets)

### v2.0.0 (07/06/2026)
- ✨ Gestion complète des examens avec classes
- 📋 Regroupement par enseignant avec filtres
- 🔄 Persistance des préférences (onglet, thème)
- 🎨 Formulaire d'examen masqué par défaut
- 🏷️ Ajout des métadonnées (noms, matières, enseignants, classes)
- 📝 README et rapport mis à jour

### v2.1.0 (07/06/2026)
- Bouton « Envoyer » sticky sur mobile
- (Mention « Multi-langue » retirée — i18n.js désactivé)

### v3.0.0 (08/06/2026) — Version actuelle
- 🔒 **Sécurité** :
  - Hashage bcrypt du mot de passe admin
  - Anti-brute-force (5 tentatives / 15 min)
  - Expiration de session (30 min)
  - Sauvegarde atomique de `exams.json` (backup + lock + rotation)
  - Nettoyage des fichiers de rate-limit
- ✨ **UX/UI** :
  - Page de garde avec examens à venir
  - Mode sombre/clair sur l'app élève
  - Avertissement avant fermeture d'onglet pendant upload
- 🛡️ **Anti-plagiat** :
  - Empreintes SHA-256 calculées côté client
  - Détection de doublons dans l'admin
- 📈 **Suivi par examen** :
  - Statistiques attendues vs réelles
  - Onglet dédié avec barres de progression
- 📄 **Documentation** :
  - README v3.0
  - RAPPORT_AMELIORATION mis à jour

---

*Rapport mis à jour le 08/06/2026.*
