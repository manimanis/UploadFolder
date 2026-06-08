# 📤 Upload de Travaux

**Version :** 3.0.0  
**License :** MIT  
**Technologies :** Vue.js 2, PHP 8, JSZip, Bootstrap 5, CSS3, Web Crypto API

---

## 🎯 Présentation

Application web permettant aux élèves d'envoyer leurs travaux (dossiers, fichiers ou code source) via un formulaire en ligne. Une interface d'administration permet aux enseignants de gérer et télécharger les fichiers, planifier des examens, et détecter les plagiats potentiels.

---

## ✨ Fonctionnalités

### Côté élève (`index.php`)

- **Page de garde publique** affichant les examens à venir (7 prochains jours)
- **Upload de dossiers** (drag & drop), fichiers individuels ou code source collé
- **Compression automatique** des fichiers en ZIP côté client (JSZip)
- **Empreintes SHA-256** calculées à la volée pour chaque fichier (anti-plagiat)
- **Avertissement avant fermeture d'onglet** pendant un upload
- Sélection de la classe, saisie du poste, choix d'un examen planifié
- **Mode sombre/clair** persistant dans `localStorage`
- Stepper visuel guidant l'utilisateur étape par étape
- Feedback visuel (confettis) en cas de succès
- Limitation de débit (1 upload / 30s)
- Navigation clavier (flèches, Enter, Espace) sur les cartes radio
- Bouton « Envoyer » sticky sur mobile
- Champs date, date début et date fin (examen) avec types HTML5 natifs

### Côté administration (`admin.php`)

- **Authentification sécurisée** : mot de passe hashé en bcrypt, anti-brute-force (5 tentatives / 15 min), expiration de session (30 min)
- **Arborescence** : navigation par date / classe / poste avec cases à cocher
- **Filtre par classe** : filtrage rapide dans l'arborescence
- **Statistiques** : graphiques par classe, résumé des fichiers et espace utilisé
- **Examens** : gestion complète (CRUD) avec champ **classes** et regroupement par **enseignant**
- **Suivi par examen** : progression attendue vs réelle (classe par classe)
- **Détection de doublons** : SHA-256 des fichiers identiques regroupés
- **Journal** : historique des uploads
- Export CSV, téléchargement multiple, thème sombre, rafraîchissement auto
- Persistance des préférences (onglet, thème) dans `localStorage`

---

## 🔒 Sécurité (v3.0)

- **Mot de passe admin hashé bcrypt** stocké dans `data/admin.hash` (auto-généré au 1er lancement)
- **Anti-brute-force** : 5 tentatives max par IP, blocage 15 minutes, journalisation
- **Expiration de session** : 30 minutes d'inactivité, régénération d'ID à la connexion
- **Token CSRF** sur chaque upload
- Validation MIME + ouverture réelle ZIP avec ZipArchive
- `php_flag engine off` dans `upload_folder/` (auto-généré via `.htaccess`)
- Assainissement des chemins (`..` bloqué)
- Rate limiting (1 upload / 30s par IP)
- **Sauvegarde atomique** de `exams.json` : backup horodaté + écriture via fichier temporaire + verrou (lock) + conservation des 5 derniers backups
- **Nettoyage automatique** des fichiers de rate-limit expirés (1% de chance par upload)

---

## 📁 Stockage

L'arborescence des fichiers uploadés :

```
upload_folder/
  └── 20260115/             (YYYYMMDD)
      └── 2INFO1/           (classe)
          └── Poste 1/      (poste)
              └── 20260115_142530_127-0-0-1.zip
```

Métadonnées enregistrées :
- `upload.log` : journal des uploads (IP, classe, poste, taille, fichier)
- `data/hashes.log` : empreintes SHA-256 par upload (pour détection de doublons/plagiat)
- `data/login_attempts.log` : tentatives de connexion admin
- `exams.json.bak.*` : 5 derniers backups de la base d'examens

---

## 🏗️ Architecture

```
UploadFolder/
├── index.php          # Application principale (upload élève)
├── admin.php          # Interface d'administration
├── api_admin.php      # API REST de l'administration
├── upload.php         # Backend de traitement des uploads
├── classes.json       # Liste des classes disponibles
├── exams.json         # Examens et métadonnées
├── upload.log         # Journalisation des uploads
├── upload_folder/     # Dossier de stockage
├── data/              # Données internes (auto-créé)
│   ├── admin.hash         # Hash bcrypt du mot de passe admin
│   ├── login_attempts.log # Log des tentatives de login
│   └── hashes.log         # Empreintes SHA-256 par upload
├── .htaccess          # Règles de sécurité
├── assets/
│   ├── apps/
│   │   ├── admin.js   # App Vue.js admin
│   │   └── app.js     # App Vue.js élève
│   ├── css/
│   │   ├── admin.css
│   │   └── style.css
│   ├── js/
│   │   ├── jszip.min.js
│   │   └── vue.min.js
│   └── images/
├── README.md
└── RAPPORT_AMELIORATION.md
```

---

## 🚀 Installation

### Prérequis
- PHP 8.0+ (extension `zip` requise)
- Serveur Apache ou Nginx
- Navigateur supportant Web Crypto API (SHA-256)

### Procédure
1. Cloner le dépôt dans le dossier du serveur web
2. Configurer `php.ini` :
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 120
   ```
3. Accéder à `http://localhost/UploadFolder/index.php`
4. Administration : `http://localhost/UploadFolder/admin.php`
   - Mot de passe par défaut : `admin123` (modifié au 1er accès, stocké en hash bcrypt)

> ⚠️ Le dossier `upload_folder/`, le dossier `data/` et les fichiers `.htaccess` sont créés automatiquement au premier usage.

---

## 🔧 Personnalisation

### Changer le mot de passe admin
```bash
# 1. Calculer le hash bcrypt
php -r "echo password_hash('votre_nouveau_mdp', PASSWORD_BCRYPT);"

# 2. Le mettre dans data/admin.hash (en remplaçant la valeur existante)
echo '$2y$10$...votre_hash...' > data/admin.hash
```

### Limites
- Taille max fichier : 100 Mo (vérifiée côté client ET serveur)
- Nombre max fichiers : 1000 par envoi
- Longueur max code : 100 000 caractères
- Rate limit : 1 upload / 30s par IP

---

## 📄 License

MIT © 2026 — Voir le fichier `LICENSE` pour plus de détails.

---

## 👤 Auteur

**M. Mohamed Anis MANI**  
*Enseignant en Informatique*
