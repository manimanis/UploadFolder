# 📤 Upload de Travaux

**Version :** 2.1.0  
**License :** MIT  
**Technologies :** Vue.js 2, PHP 8, JSZip, Bootstrap 5, CSS3

---

## 🎯 Présentation

Application web permettant aux élèves d'envoyer leurs travaux (dossiers, fichiers ou code source) via un formulaire en ligne. Une interface d'administration permet aux enseignants de gérer et télécharger les fichiers, ainsi que de planifier des examens.

---

## ✨ Fonctionnalités

### Côté élève (`index.php`)
- Upload de dossiers (drag & drop), fichiers individuels ou code source collé
- Compression automatique des fichiers en ZIP côté client
- Sélection de la classe et saisie du poste
- Stepper visuel guidant l'utilisateur étape par étape
- Feedback visuel (confettis) en cas de succès
- Limitation de débit (1 upload / 30s)
- Navigation clavier (flèches, Enter, Espace) sur les cartes radio
- **Bouton « Envoyer » sticky** sur mobile
- **Multi-langue** (FR/EN) avec persistance dans `localStorage`

### Côté administration (`admin.php`)
- **Arborescence** : navigation par date / classe / poste avec cases à cocher pour sélection multiple
- **Filtre par classe** : filtrage rapide dans l'arborescence
- **Statistiques** : graphiques par classe, résumé des fichiers et espace utilisé
- **Examens** : gestion complète des examens (CRUD) avec champ **classes** et regroupement par **enseignant**
- **Journal** : historique des uploads
- Export CSV, téléchargement multiple, thème sombre, rafraîchissement auto
- Persistance des préférences (onglet actif, thème, langue) dans `localStorage`
- **Multi-langue** (FR/EN) sur toute l'interface

---

## 🌍 Multi-langue

Le système i18n (`assets/js/i18n.js`) supporte le français et l'anglais :
- Boutons FR/EN dans le coin supérieur des deux pages
- Langue sauvegardée dans `localStorage` et restaurée au chargement
- Toutes les chaînes de texte utilisent `t('clé')` pour la réactivité

---

## 🏗️ Architecture

```
UploadFolder/
├── index.php          # Application principale (upload élève)
├── admin.php          # Interface d'administration
├── api_admin.php      # API REST de l'administration
├── upload.php         # Backend de traitement des uploads
├── classes.json       # Liste des classes disponibles
├── exams.json         # Examens et métadonnées (noms, matières, enseignants, classes)
├── upload.log         # Journalisation des uploads
├── upload_folder/     # Dossier de stockage des fichiers uploadés
├── .htaccess          # Règles de sécurité (protection upload_folder)
├── assets/
│   ├── apps/
│   │   ├── admin.js   # App Vue.js de l'administration
│   │   └── app.js     # App Vue.js de l'upload
│   ├── css/
│   │   ├── admin.css
│   │   └── style.css
│   ├── js/
│   │   ├── i18n.js    # Module de traduction (FR/EN)
│   │   ├── vue.min.js
│   │   └── jszip.min.js
│   └── images/
├── README.md
└── RAPPORT_AMELIORATION.md
```

---

## 🚀 Installation

### Prérequis
- PHP 8.0+ (extension `zip` requise)
- Serveur Apache ou Nginx

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
   - Mot de passe par défaut : `admin123`

> ⚠️ Le dossier `upload_folder/` et le fichier `.htaccess` sont créés automatiquement au premier upload.

---

## 🔒 Sécurité

- **Configuration PHP vérifiée au démarrage** (`upload_max_filesize`, `post_max_size`, extension `zip`)
- Validation du nom de fichier uploadé (regex, longueur max, interdiction de `.`/`..`)
- Protection CSRF par token
- Validation MIME et vérification réelle des archives ZIP
- `php_flag engine off` dans `upload_folder/` (auto-généré via `.htaccess`)
- Assainissement des chemins (`..` bloqué)
- Rate limiting (1 upload / 30s par IP)
- Journalisation complète des uploads

---

## 📋 Examens

Les examens sont stockés dans `exams.json` avec les métadonnées suivantes :
- Noms d'examens, matières, enseignants (avec autocomplete)
- **Classes associées** (séparées par des virgules)
- Chaque examen peut être lié à plusieurs classes
- Regroupement par enseignant avec filtrage par classe
- Formulaire masqué par défaut, affiché au clic

Les listes de suggestions sont automatiquement mises à jour lors de l'ajout ou la suppression d'examens.

---

## 📄 License

MIT © 2026 — Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 👤 Auteur

**M. Mohamed Anis MANI**  
*Enseignant en Informatique*
