<?php
declare(strict_types=1);
session_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — Upload de Travaux</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="icon"
    href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>

<body>
  <div id="admin-app">
    <transition name="fade">
      <div v-if="toast.show" class="toast-msg" :class="toast.type">{{ toast.message }}</div>
    </transition>

    <transition name="fade">
      <div v-if="showDetails" class="file-details-overlay" @click.self="closeDetails">
        <div class="file-details-card">
          <h5>📄 Détails du fichier</h5>
          <hr>
          <p><strong>Nom :</strong> {{ showDetails.name }}</p>
          <p><strong>Classe :</strong> {{ showDetails.classe }}</p>
          <p><strong>Poste :</strong> {{ showDetails.poste }}</p>
          <p><strong>Date :</strong> {{ showDetails.date }}</p>
          <p><strong>Taille :</strong> {{ showDetails.sizeFormatted }}</p>
          <p><strong>IP :</strong> {{ parseIpFromFilename(showDetails.name) }}</p>
          <p><strong>Modification :</strong> {{ showDetails.mtime }}</p>
          <div class="mt-3 d-flex gap-2">
            <button @click="downloadFile(showDetails.relativePath)" class="btn btn-primary btn-sm">⬇
              Télécharger</button>
            <button @click="closeDetails" class="btn btn-outline-secondary btn-sm">Fermer</button>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="!authenticated" class="login-wrapper">
      <div class="login-card">
        <h2 class="text-center mb-4">⚙️ Administration</h2>
        <form @submit.prevent="login">
          <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" id="password" class="form-control" v-model="password" autofocus required>
          </div>
          <div v-if="loginError" class="alert alert-danger">{{ loginError }}</div>
          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            {{ loading ? 'Connexion...' : 'Se connecter' }}
          </button>
        </form>
      </div>
    </div>

    <div v-else class="container my-4" style="max-width: 900px;">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 style="margin-bottom: 0;">⚙️ Administration</h1>
        <div class="d-flex gap-2 flex-wrap">
          <a href="index.php" class="btn btn-outline-secondary btn-sm">← Retour à l'app</a>
          <button @click="toggleDarkMode" class="btn btn-outline-secondary btn-sm">{{ darkMode ? '☀️' : '🌙' }}</button>
          <button @click="toggleAutoRefresh" class="btn btn-sm"
            :class="autoRefresh ? 'btn-success' : 'btn-outline-secondary'">{{ autoRefresh ? '⏸ Auto' : '🔄 Auto'
            }}</button>
          <button @click="loadData" class="btn btn-outline-secondary btn-sm">🔄</button>
          <button @click="logout" class="btn btn-outline-danger btn-sm">Déconnexion</button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Chargement...</p>
      </div>

      <template v-else>
        <div class="admin-tabs">
          <button class="admin-tab" :class="{ active: activeTab === 'tree' }" @click="activeTab = 'tree'">📁
            Arborescence</button>
          <button class="admin-tab" :class="{ active: activeTab === 'stats' }" @click="activeTab = 'stats'">📊
            Statistiques</button>
          <button class="admin-tab" :class="{ active: activeTab === 'log' }" @click="activeTab = 'log'; loadLog()">📝
            Journal</button>
          <button class="admin-tab" :class="{ active: activeTab === 'exams' }"
            @click="activeTab = 'exams'; initExamsTab()">📋
            Examens</button>
          <button class="admin-tab" :class="{ active: activeTab === 'examstats' }"
            @click="activeTab = 'examstats'; loadExamStats()">📈
            Suivi</button>
        </div>

        <div class="row mb-4">
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.nbClasses }}</h3>
              <p>Total classes</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.total_files }}</h3>
              <p>Total fichiers</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.totalSizeFormatted }}</h3>
              <p>Espace total</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.nbDates }}</h3>
              <p>Jours actifs</p>
            </div>
          </div>
        </div>

        <div class="search-bar" v-if="activeTab === 'tree'">
          <span>🔍</span>
          <input type="text" v-model="searchQuery" placeholder="Rechercher une classe, poste ou fichier...">
          <button v-if="searchQuery" @click="searchQuery = ''" class="btn btn-sm btn-outline-secondary">✕</button>
        </div>

        <!-- TAB: Arborescence -->
        <template v-if="activeTab === 'tree'">
          <div class="admin-toolbar" v-if="allFiles.length > 0">
            <button @click="downloadSelected" class="btn btn-primary btn-sm" :disabled="deleting">⬇ Arborescence
              ({{ selectedCount }})</button>
            <button @click="exportCsv" class="btn btn-outline-primary btn-sm">📥 Export CSV</button>
            <span class="text-muted ms-auto" style="font-size: 0.85rem">{{ allFiles.length }} entrée(s)</span>
          </div>
          <div v-for="(dateData, dateName) in filteredDates" :key="dateName" class="table-section">
            <h5 class="d-flex align-items-center justify-content-between">
              <span style="cursor: pointer" @click="openClassFolder(dateName)" title="Ouvrir dans l'explorateur">📅 {{
                dateName }}</span>
              <span class="badge bg-primary">{{ dateData.totalFiles }} — {{ dateData.totalSizeFormatted
                }}</span>
            </h5>
            <div v-for="(postes, className) in dateData.classes" :key="className" class="ms-3 mt-3 mb-2">
              <h6 class="folder-tree d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input" @change="toggleClassFiles($event, className, dateName)">
                <span class="folder" style="cursor: pointer" @click="openClassFolder(dateName, className)"
                  title="Ouvrir dans l'explorateur">📁 {{ className }}</span>
              </h6>
              <table class="table table-sm table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width: 40px"></th>
                    <th>Poste</th>
                    <th class="text-center">Total fichiers</th>
                    <th class="text-end">Taille</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(posteData, posteName) in postes" :key="posteName">
                    <tr>
                      <td><input type="checkbox" class="form-check-input"
                          @change="togglePosteFiles($event, dateName, className, posteName)"></td>
                      <td class="folder-tree"><span class="folder" style="cursor: pointer"
                          @click="openClassFolder(dateName, className, posteName)" title="Ouvrir dans l'explorateur">📂
                          {{ posteName }}</span></td>
                      <td class="text-center"><span class="badge bg-secondary">{{ posteData.files }}</span></td>
                      <td class="text-end"><span class="size">{{ posteData.sizeFormatted }}</span></td>
                      <td></td>
                    </tr>
                    <tr v-for="zipFile in posteData.fileList" :key="zipFile.name">
                      <td><input type="checkbox" class="form-check-input" :checked="isChecked(zipFile.relativePath)"
                          @change="toggleFile($event, zipFile)"></td>
                      <td class="folder-tree ps-4" style="cursor: pointer"
                        @click="showFileDetails({ name: zipFile.name, classe: className, poste: posteName, date: dateName, size: zipFile.size, sizeFormatted: zipFile.sizeFormatted, mtime: zipFile.mtime, relativePath: zipFile.relativePath })">
                        <span class="file">📄 {{ zipFile.name }}</span>
                      </td>
                      <td class="text-center"></td>
                      <td class="text-end"><span class="size">{{ zipFile.sizeFormatted }}</span></td>
                      <td class="text-end">
                        <button @click="downloadFile(zipFile.relativePath)"
                          class="btn btn-sm btn-outline-primary btn-delete" title="Télécharger">⬇</button>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="Object.keys(filteredDates).length === 0" class="table-section text-center text-muted py-5">
            <p>Aucun fichier trouvé.</p>
          </div>
        </template>

        <!-- TAB: Statistiques -->
        <template v-if="activeTab === 'stats'">
          <div class="table-section">
            <h5>📊 Fichiers par classe</h5>
            <div v-if="classChart.length === 0" class="text-muted py-3">Aucun fichier trouvé.</div>
            <div v-for="item in classChart" :key="item.name" class="chart-bar">
              <span class="bar-label">{{ item.name }}</span>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: item.percent + '%' }">{{ item.files }} Total fichiers</div>
              </div>
              <span class="size" style="width: 80px; text-align: right;">{{ item.size }}</span>
            </div>
          </div>
          <div class="table-section">
            <h5>📈 Résumé</h5>
            <table class="table">
              <tbody>
                <tr>
                  <td>Total classes</td>
                  <td class="text-end"><strong>{{ stats.nbClasses }}</strong></td>
                </tr>
                <tr>
                  <td>Total fichiers</td>
                  <td class="text-end"><strong>{{ stats.total_files }}</strong></td>
                </tr>
                <tr>
                  <td>Espace total</td>
                  <td class="text-end"><strong>{{ stats.totalSizeFormatted }}</strong></td>
                </tr>
                <tr>
                  <td>Jours actifs</td>
                  <td class="text-end"><strong>{{ stats.nbDates }}</strong></td>
                </tr>
                <tr v-if="stats.total_files > 0">
                  <td>Taille moyenne / fichier</td>
                  <td class="text-end"><strong>{{ formatSize(Math.round(stats.total_size / stats.total_files))
                      }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- TAB: Examens -->
        <template v-if="activeTab === 'exams'">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">📋 Examens ({{ exams.length }})</h5>
            <div class="d-flex gap-2">
              <button v-if="!showForm" @click="showAddForm" class="btn btn-primary btn-sm">➕ Ajouter</button>
              <button @click="loadExams" class="btn btn-outline-secondary btn-sm">🔄</button>
            </div>
          </div>
          <div class="table-section" v-if="showForm">
            <h5 class="mb-3">📋 {{ examForm.id ? 'Modifier un examen' : 'Ajouter un examen' }}</h5>
            <form @submit.prevent="saveExam">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="exam-name" class="form-label">Nom de l'examen</label>
                  <input type="text" id="exam-name" class="form-control" list="exam-names" v-model="examForm.name"
                    required placeholder="Ex: Devoir de controle">
                  <datalist id="exam-names">
                    <option v-for="name in meta.noms" :key="name" :value="name"></option>
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label for="exam-date" class="form-label">Date</label>
                  <input type="date" id="exam-date" class="form-control" v-model="examForm.date" required>
                </div>
                <div class="col-md-3">
                  <label for="exam-subject" class="form-label">Matière</label>
                  <input type="text" id="exam-subject" class="form-control" list="exam-subjects"
                    v-model="examForm.subject" required placeholder="Ex: Informatique">
                  <datalist id="exam-subjects">
                    <option v-for="subject in meta.matieres" :key="subject" :value="subject"></option>
                  </datalist>
                </div>
                <div class="col-md-3">
                  <label for="exam-teacher" class="form-label">Enseignant</label>
                  <input type="text" id="exam-teacher" class="form-control" list="exam-teachers"
                    v-model="examForm.teacher" required placeholder="Ex: M. Mohamed Anis MANI">
                  <datalist id="exam-teachers">
                    <option v-for="teacher in meta.enseignants" :key="teacher" :value="teacher"></option>
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label for="exam-classes" class="form-label">Classes (séparées par des virgules)</label>
                  <input type="text" id="exam-classes" class="form-control" list="exam-classes-list"
                    v-model="examForm.classes" placeholder="Ex: 2INFO1, 3TECH1">
                  <datalist id="exam-classes-list">
                    <option v-for="cls in meta.classes" :key="cls" :value="cls"></option>
                  </datalist>
                </div>
                <div class="col-md-3">
                  <label for="exam-time-start" class="form-label">Heure début</label>
                  <input type="time" id="exam-time-start" class="form-control" v-model="examForm.time_start" required>
                </div>
                <div class="col-md-3">
                  <label for="exam-time-end" class="form-label">Heure fin</label>
                  <input type="time" id="exam-time-end" class="form-control" v-model="examForm.time_end" required>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ examForm.id ? '✅ Modifier' : '➕ Ajouter'
                  }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                  @click="cancelForm">Annuler</button>
              </div>
            </form>
          </div>

          <div class="table-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">📋 Examens ({{ exams.length }})</h5>
              <button @click="loadExams" class="btn btn-outline-secondary btn-sm">🔄</button>
            </div>
            <div class="my-2 d-flex gap-1 flex-wrap">
              <button class="btn btn-sm" :class="selectedTeacher === '' ? 'btn-primary' : 'btn-outline-primary'"
                @click="selectedTeacher = ''">Tous</button>
              <button class="btn btn-sm" :class="selectedTeacher === enseignant ? 'btn-primary' : 'btn-outline-primary'"
                v-for="enseignant in meta.enseignants" :key="enseignant"
                @click="selectEnseignant(enseignant)">{{ enseignant }}</button>
            </div>
            <div v-if="exams.length === 0" class="text-center text-muted py-4">
              <p>Aucun examen enregistré.</p>
            </div>
            <template v-else>
              <div v-for="enseignant in meta.enseignants" :key="enseignant">
                <div v-if="getExamsByTeacher(enseignant).length > 0">
                  <h6 class="mt-3 mb-2">{{ enseignant }} ({{ getExamsByTeacher(enseignant).length }})</h6>
                  <table class="table table-sm table-hover align-middle">
                    <thead>
                      <tr>
                        <th width="30%">Nom</th>
                        <th width="15%">Date / Période</th>
                        <th width="25%">Matière / Classes</th>
                        <th width="15%" class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="exam in getExamsByTeacher(enseignant)" :key="exam.id">
                        <td><strong>{{ exam.name }}</strong></td>
                        <td>{{ exam.date }}<br>{{ exam.time_start }} — {{ exam.time_end }}</td>
                        <td>{{ exam.subject }}<br>{{ (exam.classes || []).join(', ') }}</td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-primary" @click="editExam(exam)">✏️</button>
                          <button class="btn btn-sm btn-outline-danger" @click="deleteExam(exam)">🗑️</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
          </div>
        </template>

        <!-- TAB: Journal -->
        <template v-if="activeTab === 'log'">
          <div class="admin-toolbar">
            <button @click="loadLog" class="btn btn-outline-secondary btn-sm">🔄 Rafraîchir</button>
            <span class="text-muted" style="font-size: 0.85rem">{{ logLines.length }} entrée(s)</span>
          </div>
          <div class="log-viewer" v-if="logLines.length > 0">
            <div class="log-line" v-for="(line, idx) in logLines" :key="idx">{{ line }}</div>
          </div>
          <div v-else class="table-section text-center text-muted py-5">
            <p>Aucune entrée de journal.</p>
          </div>
        </template>

        <!-- TAB: Suivi par examen (#28) -->
        <template v-if="activeTab === 'examstats'">
          <div class="table-section">
            <h5>📈 Suivi des examens</h5>
            <p class="text-muted" style="font-size: 0.9rem">
              Visualisez combien d'élèves ont rendu leur travail pour chaque examen planifié.
            </p>
            <div v-if="examStats.length === 0" class="text-muted py-3">
              Aucun examen planifié.
            </div>
            <div v-for="exam in examStats" :key="exam.id" class="exam-stat-row">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                  <strong>{{ exam.name }}</strong>
                  <span class="text-muted ms-2" style="font-size: 0.85rem">
                    {{ exam.subject }} — {{ exam.date }} {{ exam.time_start }}–{{ exam.time_end }}
                  </span>
                </div>
                <span class="badge" :class="examCompletionPercent(exam) >= 100 ? 'bg-success' : 'bg-primary'">
                  {{ exam.actual_uploads }} / {{ exam.expected_uploads }}
                </span>
              </div>
              <div class="progress" style="height: 8px;">
                <div class="progress-bar" :class="examCompletionPercent(exam) >= 100 ? 'bg-success' : 'bg-primary'"
                  :style="{ width: examCompletionPercent(exam) + '%' }"></div>
              </div>
              <div class="text-muted mt-1" style="font-size: 0.8rem">
                Classes : {{ (exam.classes_with_uploads || []).join(', ') || 'aucune' }}
                <span v-if="(exam.classes_with_uploads || []).length < (exam.classes || []).length">
                  (manquant : {{ (exam.classes || []).filter(c => !(exam.classes_with_uploads || []).includes(c)).join(', ') }})
                </span>
              </div>
            </div>
          </div>

          <!-- #27 Détection de doublons -->
          <div class="table-section" v-if="duplicateHashes.length > 0">
            <h5>🔍 Fichiers en doublon détectés</h5>
            <p class="text-muted" style="font-size: 0.9rem">
              Les fichiers suivants ont une empreinte SHA-256 identique et pourraient indiquer un plagiat.
            </p>
            <div v-for="dup in duplicateHashes" :key="dup.hash" class="duplicate-row">
              <code class="d-block text-truncate" style="font-size: 0.8rem">{{ dup.hash }}</code>
              <strong>{{ dup.name }}</strong> — {{ dup.uploads.length }} envois
              <ul class="mt-1 mb-0" style="font-size: 0.85rem">
                <li v-for="up in dup.uploads" :key="up.file">
                  {{ up.date }} — classe <code>{{ up.classe }}</code>, poste <code>{{ up.poste }}</code> (IP <code>{{ up.ip }}</code>)
                </li>
              </ul>
            </div>
          </div>
        </template>

      </template>
    </div>
  </div>

  <script src="assets/js/vue.min.js"></script>
  <script src="assets/apps/admin.js"></script>
</body>

</html>