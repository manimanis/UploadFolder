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
          <h5>📄 {{ t('file_details_title') }}</h5>
          <hr>
          <p><strong>{{ t('file_details_name') }} :</strong> {{ showDetails.name }}</p>
          <p><strong>{{ t('file_details_class') }} :</strong> {{ showDetails.classe }}</p>
          <p><strong>{{ t('file_details_station') }} :</strong> {{ showDetails.poste }}</p>
          <p><strong>{{ t('file_details_date') }} :</strong> {{ showDetails.date }}</p>
          <p><strong>{{ t('file_details_size') }} :</strong> {{ showDetails.sizeFormatted }}</p>
          <p><strong>IP :</strong> {{ parseIpFromFilename(showDetails.name) }}</p>
          <p><strong>{{ t('file_details_modified') }} :</strong> {{ showDetails.mtime }}</p>
          <div class="mt-3 d-flex gap-2">
            <button @click="downloadFile(showDetails.relativePath)" class="btn btn-primary btn-sm">⬇
              {{ t('btn_download') }}</button>
            <button @click="closeDetails" class="btn btn-outline-secondary btn-sm">{{ t('btn_close') }}</button>
          </div>
        </div>
      </div>
    </transition>

    <div v-if="!authenticated" class="login-wrapper">
      <div class="login-card">
        <h2 class="text-center mb-4">⚙️ {{ t('admin_title') }}</h2>
        <form @submit.prevent="login">
          <div class="mb-3">
            <label for="password" class="form-label">{{ t('admin_password') }}</label>
            <input type="password" id="password" class="form-control" v-model="password" autofocus required>
          </div>
          <div v-if="loginError" class="alert alert-danger">{{ loginError }}</div>
          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            {{ loading ? 'Connexion...' : t('admin_login') }}
          </button>
        </form>
      </div>
    </div>

    <div v-else class="container my-4" style="max-width: 900px;">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 style="margin-bottom: 0;">⚙️ {{ t('admin_title') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
          <a href="index.php" class="btn btn-outline-secondary btn-sm">← {{ t('admin_btn_back') }}</a>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-sm" :class="lang === 'fr' ? 'btn-primary' : 'btn-outline-primary'" @click="setLang('fr')">FR</button>
            <button class="btn btn-sm" :class="lang === 'en' ? 'btn-primary' : 'btn-outline-primary'" @click="setLang('en')">EN</button>
          </div>
          <button @click="toggleDarkMode" class="btn btn-outline-secondary btn-sm">{{ darkMode ? '☀️' : '🌙' }}</button>
          <button @click="toggleAutoRefresh" class="btn btn-sm"
            :class="autoRefresh ? 'btn-success' : 'btn-outline-secondary'">{{ autoRefresh ? '⏸ Auto' : '🔄 Auto'
            }}</button>
          <button @click="loadData" class="btn btn-outline-secondary btn-sm">🔄</button>
          <button @click="logout" class="btn btn-outline-danger btn-sm">{{ t('admin_logout') }}</button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">{{ t('admin_loading') }}</p>
      </div>

      <template v-else>
        <div class="admin-tabs">
          <button class="admin-tab" :class="{ active: activeTab === 'tree' }" @click="activeTab = 'tree'">📁
            {{ t('admin_tab_tree') }}</button>
          <button class="admin-tab" :class="{ active: activeTab === 'stats' }" @click="activeTab = 'stats'">📊
            {{ t('admin_tab_stats') }}</button>
          <button class="admin-tab" :class="{ active: activeTab === 'log' }" @click="activeTab = 'log'; loadLog()">📝
            {{ t('admin_tab_log') }}</button>
          <button class="admin-tab" :class="{ active: activeTab === 'exams' }"
            @click="activeTab = 'exams'; initExamsTab()">📋
            {{ t('admin_tab_exams') }}</button>
        </div>

        <div class="row mb-4">
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.nbClasses }}</h3>
              <p>{{ t('stats_total_classes') }}</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.total_files }}</h3>
              <p>{{ t('stats_total_files') }}</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.totalSizeFormatted }}</h3>
              <p>{{ t('stats_total_size') }}</p>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <div class="stat-card">
              <h3>{{ stats.nbDates }}</h3>
              <p>{{ t('stats_total_days') }}</p>
            </div>
          </div>
        </div>

        <div class="search-bar" v-if="activeTab === 'tree'">
          <span>🔍</span>
          <input type="text" v-model="searchQuery" :placeholder="t('admin_search_placeholder')">
          <button v-if="searchQuery" @click="searchQuery = ''" class="btn btn-sm btn-outline-secondary">✕</button>
        </div>

        <!-- TAB: Arborescence -->
        <template v-if="activeTab === 'tree'">
          <div class="admin-toolbar" v-if="allFiles.length > 0">
            <button @click="downloadSelected" class="btn btn-primary btn-sm" :disabled="deleting">⬇ {{ t('admin_tab_tree') }}
              ({{ selectedCount }})</button>
            <button @click="exportCsv" class="btn btn-outline-primary btn-sm">{{ t('export_csv') }}</button>
            <span class="text-muted ms-auto" style="font-size: 0.85rem">{{ allFiles.length }} {{ t('log_entries') }}</span>
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
                    <th>{{ t('file_details_station') }}</th>
                    <th class="text-center">{{ t('stats_total_files') }}</th>
                    <th class="text-end">{{ t('file_details_size') }}</th>
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
                          class="btn btn-sm btn-outline-primary btn-delete" :title="t('btn_download')">⬇</button>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="Object.keys(filteredDates).length === 0" class="table-section text-center text-muted py-5">
            <p>{{ t('admin_no_files') }}</p>
          </div>
        </template>

        <!-- TAB: Statistiques -->
        <template v-if="activeTab === 'stats'">
          <div class="table-section">
            <h5>📊 {{ t('stats_title') }}</h5>
            <div v-if="classChart.length === 0" class="text-muted py-3">{{ t('admin_no_files') }}</div>
            <div v-for="item in classChart" :key="item.name" class="chart-bar">
              <span class="bar-label">{{ item.name }}</span>
              <div class="bar-track">
                <div class="bar-fill" :style="{ width: item.percent + '%' }">{{ item.files }} {{ t('stats_total_files') }}</div>
              </div>
              <span class="size" style="width: 80px; text-align: right;">{{ item.size }}</span>
            </div>
          </div>
          <div class="table-section">
            <h5>📈 {{ t('stats_summary') }}</h5>
            <table class="table">
              <tbody>
                <tr>
                  <td>{{ t('stats_total_classes') }}</td>
                  <td class="text-end"><strong>{{ stats.nbClasses }}</strong></td>
                </tr>
                <tr>
                  <td>{{ t('stats_total_files') }}</td>
                  <td class="text-end"><strong>{{ stats.total_files }}</strong></td>
                </tr>
                <tr>
                  <td>{{ t('stats_total_size') }}</td>
                  <td class="text-end"><strong>{{ stats.totalSizeFormatted }}</strong></td>
                </tr>
                <tr>
                  <td>{{ t('stats_total_days') }}</td>
                  <td class="text-end"><strong>{{ stats.nbDates }}</strong></td>
                </tr>
                <tr v-if="stats.total_files > 0">
                  <td>{{ t('stats_avg_size') }}</td>
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
            <h5 class="mb-0">📋 {{ t('admin_tab_exams') }} ({{ exams.length }})</h5>
            <div class="d-flex gap-2">
              <button v-if="!showForm" @click="showAddForm" class="btn btn-primary btn-sm">{{ t('admin_btn_add') }}</button>
              <button @click="loadExams" class="btn btn-outline-secondary btn-sm">{{ t('admin_btn_refresh') }}</button>
            </div>
          </div>
          <div class="table-section" v-if="showForm">
            <h5 class="mb-3">📋 {{ examForm.id ? t('admin_edit_exam') : t('admin_add_exam') }}</h5>
            <form @submit.prevent="saveExam">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="exam-name" class="form-label">{{ t('exam_name') }}</label>
                  <input type="text" id="exam-name" class="form-control" list="exam-names" v-model="examForm.name"
                    required :placeholder="t('exam_name_placeholder')">
                  <datalist id="exam-names">
                    <option v-for="name in meta.noms" :key="name" :value="name"></option>
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label for="exam-date" class="form-label">{{ t('exam_date') }}</label>
                  <input type="date" id="exam-date" class="form-control" v-model="examForm.date" required>
                </div>
                <div class="col-md-3">
                  <label for="exam-subject" class="form-label">{{ t('exam_subject') }}</label>
                  <input type="text" id="exam-subject" class="form-control" list="exam-subjects"
                    v-model="examForm.subject" required :placeholder="t('exam_subject_placeholder')">
                  <datalist id="exam-subjects">
                    <option v-for="subject in meta.matieres" :key="subject" :value="subject"></option>
                  </datalist>
                </div>
                <div class="col-md-3">
                  <label for="exam-teacher" class="form-label">{{ t('exam_teacher') }}</label>
                  <input type="text" id="exam-teacher" class="form-control" list="exam-teachers"
                    v-model="examForm.teacher" required :placeholder="t('exam_teacher_placeholder')">
                  <datalist id="exam-teachers">
                    <option v-for="teacher in meta.enseignants" :key="teacher" :value="teacher"></option>
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label for="exam-classes" class="form-label">{{ t('exam_classes') }}</label>
                  <input type="text" id="exam-classes" class="form-control" list="exam-classes-list"
                    v-model="examForm.classes" :placeholder="t('exam_classes_placeholder')">
                  <datalist id="exam-classes-list">
                    <option v-for="cls in meta.classes" :key="cls" :value="cls"></option>
                  </datalist>
                </div>
                <div class="col-md-3">
                  <label for="exam-time-start" class="form-label">{{ t('exam_time_start') }}</label>
                  <input type="time" id="exam-time-start" class="form-control" v-model="examForm.time_start" required>
                </div>
                <div class="col-md-3">
                  <label for="exam-time-end" class="form-label">{{ t('exam_time_end') }}</label>
                  <input type="time" id="exam-time-end" class="form-control" v-model="examForm.time_end" required>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ examForm.id ? t('admin_btn_edit') : t('admin_btn_add')
                  }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                  @click="cancelForm">{{ t('admin_btn_cancel') }}</button>
              </div>
            </form>
          </div>

          <div class="table-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">📋 {{ t('admin_tab_exams') }} ({{ exams.length }})</h5>
              <button @click="loadExams" class="btn btn-outline-secondary btn-sm">{{ t('admin_btn_refresh') }}</button>
            </div>
            <div class="my-2 d-flex gap-1 flex-wrap">
              <button class="btn btn-sm" :class="selectedTeacher === '' ? 'btn-primary' : 'btn-outline-primary'"
                @click="selectedTeacher = ''">{{ t('exam_tab_all') }}</button>
              <button class="btn btn-sm" :class="selectedTeacher === enseignant ? 'btn-primary' : 'btn-outline-primary'"
                v-for="enseignant in meta.enseignants" :key="enseignant"
                @click="selectEnseignant(enseignant)">{{ enseignant }}</button>
            </div>
            <div v-if="exams.length === 0" class="text-center text-muted py-4">
              <p>{{ t('admin_no_exams') }}</p>
            </div>
            <template v-else>
              <div v-for="enseignant in meta.enseignants" :key="enseignant">
                <div v-if="getExamsByTeacher(enseignant).length > 0">
                  <h6 class="mt-3 mb-2">{{ enseignant }} ({{ getExamsByTeacher(enseignant).length }})</h6>
                  <table class="table table-sm table-hover align-middle">
                    <thead>
                      <tr>
                        <th width="30%">{{ t('exam_col_name') }}</th>
                        <th width="15%">{{ t('exam_col_date_period') }}</th>
                        <th width="25%">{{ t('exam_col_subject_class') }}</th>
                        <th width="15%" class="text-end">{{ t('exam_col_actions') }}</th>
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
            <button @click="loadLog" class="btn btn-outline-secondary btn-sm">{{ t('log_refresh') }}</button>
            <span class="text-muted" style="font-size: 0.85rem">{{ logLines.length }} {{ t('log_entries') }}</span>
          </div>
          <div class="log-viewer" v-if="logLines.length > 0">
            <div class="log-line" v-for="(line, idx) in logLines" :key="idx">{{ line }}</div>
          </div>
          <div v-else class="table-section text-center text-muted py-5">
            <p>{{ t('log_no_entries') }}</p>
          </div>
        </template>

      </template>
    </div>
  </div>

  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/vue.min.js"></script>
  <script src="assets/apps/admin.js"></script>
</body>

</html>