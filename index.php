<?php
session_start();

// Generate a CSRF token if one doesn't exist yet
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
  <title>Envoi de Travaux</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon"
    href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📤</text></svg>">
</head>

<body>
  <!-- Drop overlay -->
  <div id="drop-overlay" class="drop-overlay d-none" aria-hidden="true">
    <div class="drop-overlay-content">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      <h2 class="mt-3">{{ t('drop_title') }}</h2>
      <p class="text-muted">{{ t('drop_subtitle') }}</p>
    </div>
  </div>
  <!-- Loading overlay -->
  <div id="loading-overlay" class="loading-spinner d-none" aria-hidden="true">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p id="loading-text">{{ t('submit_sending') }}</p>
  </div>

  <div id="confetti-container" class="confetti-container d-none" aria-hidden="true"></div>
  <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

  <main id="app" class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h1 style="margin-bottom: 0;">📤 {{ t('app_title') }}</h1>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-sm" :class="lang === 'fr' ? 'btn-primary' : 'btn-outline-primary'" @click="setLang('fr')">FR</button>
        <button type="button" class="btn btn-sm" :class="lang === 'en' ? 'btn-primary' : 'btn-outline-primary'" @click="setLang('en')">EN</button>
      </div>
    </div>

    <!-- Stepper -->
    <div class="stepper" aria-label="Progression">
      <div v-bind:class="stepperClass(0)" class="stepper-step" v-on:click="goToStep(0)">
        <div class="stepper-circle">
          <span class="stepper-number">1</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
        </div>
        <span class="stepper-label">{{ t('step_type') }}</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 1 }"></div>
      <div v-bind:class="stepperClass(1)" class="stepper-step" v-on:click="goToStep(1)">
        <div class="stepper-circle">
          <span class="stepper-number">2</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
        </div>
        <span class="stepper-label">{{ t('step_file') }}</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 2 }"></div>
      <div v-bind:class="stepperClass(2)" class="stepper-step" v-on:click="goToStep(2)">
        <div class="stepper-circle">
          <span class="stepper-number">3</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
        </div>
        <span class="stepper-label">{{ t('step_info') }}</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 3 }"></div>
      <div v-bind:class="stepperClass(3)" class="stepper-step" v-on:click="goToStep(3)">
        <div class="stepper-circle">
          <span class="stepper-number">4</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
        </div>
        <span class="stepper-label">{{ t('step_submit') }}</span>
      </div>
    </div>

    <form id="form" method="post" enctype="multipart/form-data" novalidate>
      <!-- STEP 0: Type Selection -->
      <transition name="step" mode="out-in">
      <div v-if="step === 0" class="step-card">
        <fieldset>
          <legend><span class="card-icon">📁</span>{{ t('type_legend') }}</legend>
          <div class="row">
            <div class="col-sm-4 my-1">
              <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)" v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                <input type="radio" name="type" value="dossier" v-on:click="onTypeDataClicked('dossier')"
                  v-bind:checked="typeData == 'dossier'" tabindex="-1">
                <img src="assets/images/folder.png" alt="Icône dossier">
                <span>{{ t('type_dossier') }}</span>
              </label>
            </div>
            <div class="col-sm-4 my-1">
              <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)" v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                <input type="radio" name="type" value="fichier" v-on:click="onTypeDataClicked('fichier')"
                  v-bind:checked="typeData == 'fichier'" tabindex="-1">
                <img src="assets/images/file.png" alt="Icône fichier">
                <span>{{ t('type_fichier') }}</span>
              </label>
            </div>
            <div class="col-sm-4 my-1">
              <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)" v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                <input type="radio" name="type" value="code" v-on:click="onTypeDataClicked('code')"
                  v-bind:checked="typeData == 'code'" tabindex="-1">
                <img src="assets/images/python.png" alt="Icône code">
                <span>{{ t('type_code') }}</span>
              </label>
            </div>
          </div>
        </fieldset>
      </div>
      </transition>

      <!-- STEP 1: File / Code Selection -->
      <transition name="step" mode="out-in">
      <div v-if="step === 1" class="step-card">
        <fieldset>
          <legend><span class="card-icon">📎</span>{{ t('select_legend') }}</legend>
          <div v-show="typeData == 'dossier'" id="dossier-select" class="my-2">
            <label for="files" class="form-label">{{ t('select_dossier_label') }}</label>
            <input type="file" name="files[]" id="files" multiple directory webkitdirectory moxdirectory
              class="form-control" v-on:change="onDataChanged('#files')">
          </div>
          <div v-show="typeData == 'fichier'" id="fichier-select" class="my-2">
            <label for="fichier" class="form-label">{{ t('select_fichier_label') }}</label>
            <input type="file" name="fichier" id="fichier" class="form-control" v-on:change="onDataChanged('#fichier')">
          </div>
          <div v-show="typeData == 'code'" id="code-select" class="my-2">
            <label for="code" class="form-label">{{ t('select_code_label') }}</label>
            <textarea id="code" name="code" class="form-control" rows="6" :placeholder="t('select_code_placeholder')"
              v-on:change="onDataChanged('#code')">{{code}}</textarea>
          </div>

          <div class="mt-3 d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary btn-sm" v-on:click="onPreviousStep()">
              {{ t('btn_previous') }}
            </button>
            <button type="button" class="btn btn-primary btn-sm" v-on:click="onNextStep()"
              v-if="selectedFilesInfos.length > 0">→</button>
          </div>

          <div class="files-info my-3" v-if="selectedFilesInfos.length > 0">
            <h6>{{ t('select_files_count') }} {{ selectedFilesInfos.length }} fichier(s)</h6>
            <div class="file-info" v-for="fileInfo in selectedFilesInfos" :key="fileInfo.name">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#28a745" stroke-width="2">
                <path d="M7 10l2 2 4-4" />
                <circle cx="10" cy="10" r="9" />
              </svg>
              <span class="file-name">{{ fileInfo.name }}</span>
              <span class="file-size">{{ fileInfo.size }}</span>
            </div>
          </div>
        </fieldset>
      </div>
      </transition>

      <!-- STEP 2: Class & Poste -->
      <transition name="step" mode="out-in">
      <div v-if="step === 2" class="step-card">
        <fieldset>
          <legend><span class="card-icon">🏫</span>{{ t('info_legend') }}</legend>
          <div class="my-2">
            <label for="classes" class="form-label">{{ t('info_classe_label') }}</label>
            <select id="classes" class="form-select" v-model="classe" v-on:change="onClasseChanged()" required>
              <option value="">{{ t('info_classe_placeholder') }}</option>
              <option v-for="cls in nomsClasses" v-bind:value="cls">{{cls}}</option>
              <option value="Autre">Autre</option>
            </select>
          </div>
          <div v-if="classe === 'Autre'" class="my-2">
            <label for="classe-autre" class="form-label">{{ t('info_classe_autre_label') }}</label>
            <input id="classe-autre" type="text" class="form-control" v-model="classeCustom"
              :placeholder="t('info_classe_autre_placeholder')" required>
          </div>
          <div class="my-2">
            <label for="poste" class="form-label">{{ t('info_poste_label') }}</label>
            <input id="poste" type="text" name="poste" class="form-control" v-model="poste"
              v-on:change="onPosteChanged()" :placeholder="t('info_poste_placeholder')">
          </div>
          <div class="mt-3 d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary btn-sm" v-on:click="onPreviousStep()">
              {{ t('btn_previous') }}
            </button>
            <button type="button" class="btn btn-primary btn-sm" v-on:click="onNextStep()"
              v-if="classe && poste">→</button>
          </div>
        </fieldset>
      </div>
      </transition>

      <!-- STEP 3: Submit -->
      <transition name="step" mode="out-in">
      <div v-if="step === 3" class="step-card">
        <fieldset>
          <legend><span class="card-icon">🚀</span>{{ t('submit_legend') }}</legend>
          <div class="text-center">
            <p class="mb-3 text-muted">{{ t('submit_intro') }}</p>
            <input type="submit" :value="t('submit_btn')" id="upload-btn" name="upload"
              class="btn btn-primary btn-lg" v-on:click.prevent="onUploadClicked()" :disabled="uploading" />
          </div>
          <div class="list-unstyled">
            <div><strong>{{ t('submit_classe') }}</strong> {{ classe === 'Autre' ? classeCustom : classe }}</div>
            <div><strong>{{ t('submit_poste') }}</strong> {{ poste }}</div>
            <div class="files-info my-3" v-if="selectedFilesInfos.length > 0">
              <h6>{{ t('select_files_count') }} {{ selectedFilesInfos.length }} fichier(s)</h6>
              <div class="file-info" v-for="fileInfo in selectedFilesInfosLimited" :key="fileInfo.name">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#28a745" stroke-width="2">
                  <path d="M7 10l2 2 4-4" />
                  <circle cx="10" cy="10" r="9" />
                </svg>
                <span class="file-name">{{ fileInfo.name }}</span>
                <span class="file-size">{{ formatFileSize(fileInfo.size) }}</span>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" v-on:click="onPreviousStep()">
              {{ t('btn_previous') }}
            </button>
          </div>
        </fieldset>
      </div>
      </transition>

    </form>
  </main>
  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/vue.min.js"></script>
  <script src="assets/js/jszip.min.js"></script>
  <script src="assets/apps/app.js"></script>
</body>

</html>