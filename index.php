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
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
      </svg>
      <h2 class="mt-3">Déposez vos fichiers ici</h2>
      <p class="text-muted">Ils seront automatiquement ajoutés à l'envoi</p>
    </div>
  </div>
  <!-- Loading overlay -->
  <div id="loading-overlay" class="loading-spinner d-none" aria-hidden="true">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p id="loading-text">Compression et envoi en cours...</p>
  </div>

  <div id="confetti-container" class="confetti-container d-none" aria-hidden="true"></div>
  <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

  <main id="app" class="container my-5">
    <div class="app-topbar">
      <button type="button" class="btn btn-sm btn-outline-secondary" v-on:click="toggleDarkMode()"
        v-bind:title="darkMode ? 'Mode clair' : 'Mode sombre'">
        {{ darkMode ? '☀️' : '🌙' }}
      </button>
      <a href="admin.php" class="btn btn-sm btn-outline-secondary" title="Administration">⚙️</a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <h1 style="margin-bottom: 0;">📤 Envoi de Travaux</h1>
    </div>

    <!-- Page de garde (#47) : Examens à venir -->
    <div v-if="step === 0 && upcomingExams.length > 0" class="step-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>📅 Examens à venir</strong>
        <small class="text-muted">Cliquez pour pré-remplir</small>
      </div>
      <div class="landing-exams">
        <div v-for="exam in upcomingExams" v-bind:key="exam.id" class="landing-exam"
          v-on:click="selectUpcomingExam(exam)">
          <div class="le-name">{{ exam.name }} — {{ exam.subject }}</div>
          <div class="le-meta">
            {{ exam.classes.join(', ') }} • {{ exam.date }} • {{ exam.time_start }}–{{ exam.time_end }} • {{ exam.teacher }}
          </div>
        </div>
      </div>
    </div>

    <!-- Stepper -->
    <div class="stepper" aria-label="Progression">
      <div v-bind:class="stepperClass(0)" class="stepper-step" v-on:click="gotoStep(0)">
        <div class="stepper-circle">
          <span class="stepper-number">1</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>
        <span class="stepper-label">Type</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 1 }"></div>
      <div v-bind:class="stepperClass(1)" class="stepper-step" v-on:click="gotoStep(1)">
        <div class="stepper-circle">
          <span class="stepper-number">2</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>
        <span class="stepper-label">Fichier</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 2 }"></div>
      <div v-bind:class="stepperClass(2)" class="stepper-step" v-on:click="gotoStep(2)">
        <div class="stepper-circle">
          <span class="stepper-number">3</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>
        <span class="stepper-label">Infos</span>
      </div>
      <div class="stepper-connector" v-bind:class="{ done: step >= 3 }"></div>
      <div v-bind:class="stepperClass(3)" class="stepper-step" v-on:click="gotoStep(3)">
        <div class="stepper-circle">
          <span class="stepper-number">4</span>
          <svg class="stepper-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>
        <span class="stepper-label">Envoi</span>
      </div>
    </div>

    <form id="form" method="post" enctype="multipart/form-data" novalidate>
      <!-- STEP 0: Type Selection -->
      <transition name="step" mode="out-in">
        <div v-if="step === 0" class="step-card">
          <fieldset>
            <legend><span class="card-icon">📁</span>Je veux envoyer :</legend>
            <div class="row">
              <div class="col-sm-4 my-1">
                <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)"
                  v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                  <input type="radio" name="type" value="dossier" v-on:click="onTypeDataClicked('dossier')"
                    v-bind:checked="typeData == 'dossier'" tabindex="-1">
                  <img src="assets/images/folder.png" alt="Icône dossier">
                  <span>Dossier</span>
                </label>
              </div>
              <div class="col-sm-4 my-1">
                <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)"
                  v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                  <input type="radio" name="type" value="fichier" v-on:click="onTypeDataClicked('fichier')"
                    v-bind:checked="typeData == 'fichier'" tabindex="-1">
                  <img src="assets/images/file.png" alt="Icône fichier">
                  <span>Fichier</span>
                </label>
              </div>
              <div class="col-sm-4 my-1">
                <label class="radio-card" tabindex="0" v-on:keydown.arrow-right="focusNextRadio($event, 1)"
                  v-on:keydown.arrow-left="focusNextRadio($event, -1)">
                  <input type="radio" name="type" value="code" v-on:click="onTypeDataClicked('code')"
                    v-bind:checked="typeData == 'code'" tabindex="-1">
                  <img src="assets/images/python.png" alt="Icône code">
                  <span>Code</span>
                </label>
              </div>
            </div>

            <div class="mt-3 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                ← Retour
              </button>
              <button type="button" class="btn btn-primary btn-sm" v-on:click="onNextStep()"
                v-if="canGotoStep(1)">→</button>
            </div>
          </fieldset>
        </div>
      </transition>

      <!-- STEP 1: File / Code Selection -->
      <transition name="step" mode="out-in">
        <div v-if="step === 1" class="step-card">
          <fieldset>
            <legend><span class="card-icon">📎</span>Sélectionnez votre travail :</legend>
            <div v-show="typeData == 'dossier'" id="dossier-select" class="my-2">
              <label for="files" class="form-label">Dossier à envoyer</label>
              <input type="file" name="files[]" id="files" multiple directory webkitdirectory moxdirectory
                class="form-control" v-on:change="onDataChanged('#files')">
            </div>
            <div v-show="typeData == 'fichier'" id="fichier-select" class="my-2">
              <label for="fichier" class="form-label">Fichier à envoyer</label>
              <input type="file" name="fichier" id="fichier" class="form-control"
                v-on:change="onDataChanged('#fichier')">
            </div>
            <div v-show="typeData == 'code'" id="code-select" class="my-2">
              <label for="code" class="form-label">Copier-coller votre code source</label>
              <textarea id="code" name="code" class="form-control" rows="6" placeholder="Collez votre code ici..."
                v-on:change="onDataChanged('#code')">{{code}}</textarea>
            </div>

            <div class="mt-3 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary btn-sm" v-on:click="onPreviousStep()">
                ← Retour
              </button>
              <button type="button" class="btn btn-primary btn-sm" v-on:click="onNextStep()"
                v-if="canGotoStep(2)">→</button>
            </div>

            <div class="files-info my-3" v-if="selectedFilesInfos.length > 0">
              <h6>Fichiers sélectionnés : {{ selectedFilesInfos.length }} fichier(s)</h6>
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

      <!-- STEP 2: Exam, Class, Subject, Teacher & Poste -->
      <transition name="step" mode="out-in">
        <div v-if="step === 2" class="step-card">
          <fieldset>
            <legend><span class="card-icon">🏫</span>Vos informations</legend>

            <!-- Exam selection -->
            <div class="my-2">
              <label class="form-label">Examen</label>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm"
                  v-bind:class="curExam.id === exam.id ? 'btn-primary' : 'btn-outline-primary'"
                  v-for="exam in todayExams" v-bind:key="exam.id" v-on:click="selectExam(exam.id)">
                  <span class="fs-5 fw-bold">{{ exam.classes.join(", ") }}</span><br>
                  <span class="fs-4">{{ exam.name }}</span><br>
                  <span class="fs-6 fw-light">{{ exam.teacher }}</span>
                </button>
                <button type="button" class="btn btn-sm"
                  v-bind:class="curExam.id === 'autre' ? 'btn-primary' : 'btn-outline-primary'"
                  v-on:click="selectExam('autre')">Autre</button>
              </div>
              <p v-if="todayExams.length === 0" class="text-muted mt-1 mb-0" style="font-size:0.85rem;">Aucun examen
                prévu pour aujourd'hui.</p>
            </div>

            <div>
              <!-- Enseignant -->
              <div class="my-2">
                <label for="enseignant" class="form-label">Enseignant</label>
                <input id="enseignant" type="text" class="form-control" v-model="curExam.teacher"
                  v-on:change="onFormDataChanged()" placeholder="Ex: M. Mohamed Anis MANI" required list="enseignants">
                <datalist id="enseignants">
                  <option v-for="enseignant in teachers" v-bind:value="enseignant">{{ enseignant }}</option>
                </datalist>
              </div>

              <div class="row my-2">
                <!-- Classe -->
                <div class="col-sm-4">
                  <label for="classe-autre" class="form-label">Classe</label>
                  <input id="classe-autre" type="text" class="form-control" v-model="curExam.classes[0]"
                    v-on:change="onFormDataChanged()" placeholder="Ex: 1INFO2" required list="classes">
                  <datalist id="classes">
                    <option v-for="classe in classes" v-bind:value="classe">{{ classe }}</option>
                  </datalist>
                </div>

                <!-- Matière -->
                <div class="col-sm-4">
                  <label for="matiere" class="form-label">Matière</label>
                  <input id="matiere" type="text" class="form-control" v-model="curExam.subject"
                    v-on:change="onFormDataChanged()" placeholder="Ex: Informatique" required list="matieres">
                  <datalist id="matieres">
                    <option v-for="matiere in subjects" v-bind:value="matiere">{{ matiere }}</option>
                  </datalist>
                </div>

                <!-- Epreuve -->
                <div class="col-sm-4"><label for="epreuve" class="form-label">Epreuve</label>
                  <input id="epreuve" type="text" class="form-control" v-model="curExam.name"
                    v-on:change="onFormDataChanged()" placeholder="Ex: Informatique" required list="epreuves">
                  <datalist id="epreuves">
                    <option v-for="epreuve in sessions" v-bind:value="epreuve">{{ epreuve }}</option>
                  </datalist>
                </div>
              </div>

              <div class="row my-2">
                <!-- Date -->
                <div class="col-sm-4">
                  <label for="date" class="form-label">Date</label>
                  <input id="date" type="date" class="form-control" v-model="curExam.date"
                    v-on:change="onFormDataChanged()" required>
                </div>

                <!-- Date début (heure de début) -->
                <div class="col-sm-4">
                  <label for="date-debut" class="form-label">Date début</label>
                  <input id="date-debut" type="time" class="form-control" v-model="curExam.time_start"
                    v-on:change="onFormDataChanged()" required>
                </div>

                <!-- Date fin (heure de fin) -->
                <div class="col-sm-4">
                  <label for="date-fin" class="form-label">Date fin</label>
                  <input id="date-fin" type="time" class="form-control" v-model="curExam.time_end"
                    v-on:change="onFormDataChanged()" required>
                </div>
              </div>
            </div>

            <!-- Poste -->
            <div class="my-2">
              <label for="poste" class="form-label">Poste</label>
              <input type="text" name="poste" id="poste" class="form-select" v-model="curExam.poste"
                v-on:change="onFormDataChanged()" placeholder="Ex: Poste 1" required list="postes">
              <datalist id="postes">
                <option v-for="numPoste in 20" v-bind:value="'Poste ' + numPoste">Poste {{numPoste}}</option>
              </datalist>
            </div>

            <div class="mt-3 d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary btn-sm" v-on:click="onPreviousStep()">
                ← Retour
              </button>
              <button type="button" class="btn btn-primary btn-sm" v-on:click="onNextStep()"
                v-if="canGotoStep(3)">→</button>
            </div>
          </fieldset>
        </div>
      </transition>

      <!-- STEP 3: Submit -->
      <transition name="step" mode="out-in">
        <div v-if="step === 3" class="step-card">
          <fieldset>
            <legend><span class="card-icon">🚀</span>Confirmation</legend>
            <div class="text-center">
              <p class="mb-3 text-muted">Vérifiez vos informations avant d'envoyer :</p>
              <input type="submit" value="Envoyer mon travail" id="upload-btn" name="upload"
                class="btn btn-primary btn-lg" v-on:click.prevent="onUploadClicked()" :disabled="uploading" />
            </div>
            <div class="my-4" v-if="curExam">
              <div class="my-1" v-for="(value, key) in curExam" :key="key">
                <strong>{{ key }} :</strong> {{ value }}
              </div>
              <div class="files-info my-3" v-if="selectedFilesInfos.length > 0">
                <h6>Fichiers sélectionnés : {{ selectedFilesInfos.length }} fichier(s)</h6>
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
                ← Retour
              </button>
            </div>
          </fieldset>
        </div>
      </transition>
    </form>
  </main>
  <script src="assets/js/vue.min.js"></script>
  <script src="assets/js/jszip.min.js"></script>
  <script src="assets/apps/app.js"></script>
</body>

</html>