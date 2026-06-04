const app = new Vue({
  el: '#app',
  data: {
    step: 0,
    typeData: '',
    elem: null,
    classe: '',
    poste: 'Poste 1',
    nomsClasses: [],
    classes: {},
    uploading: false,
    selectedFilesInfos: [],
    code: '',
    files: [],
    fichier: null,
    csrfToken: '',
    classeCustom: '',

    // File size limit: 100 MB for the uncompressed data
    maxSizeBytes: 100 * 1024 * 1024
  },
  mounted: function () {
    this.fetchClasses();
    this.restoreSession();
    this.loadCsrfToken();
    // Attach drag & drop listeners on the whole document (outside Vue's #app scope)
    this._onDragOver = this.onDragOver.bind(this);
    this._onDrop = this.onDrop.bind(this);
    document.addEventListener('dragover', this._onDragOver);
    document.addEventListener('drop', this._onDrop);
  },
  beforeDestroy: function () {
    // Clean up listeners to avoid memory leaks
    document.removeEventListener('dragover', this._onDragOver);
    document.removeEventListener('drop', this._onDrop);
    if (this._onDragLeave) {
      document.removeEventListener('dragleave', this._onDragLeave);
    }
  },
  computed: {
    selectedFilesInfosLimited: function () {
      if (!this.selectedFilesInfos || this.selectedFilesInfos.length === 0) return [];
      if (this.selectedFilesInfos.length > 5) {
        return [...this.selectedFilesInfos.slice(0, 5), {
          name: '... et ' + (this.selectedFilesInfos.length - 5) + ' autres fichiers',
          size: this.selectedFilesInfos.reduce(function (acc, file) {
            return acc + file.size;
          }, 0)
        }];
      }
      return this.selectedFilesInfos;
    },
    effectiveClasse: function () {
      return this.classe === 'Autre' ? this.classeCustom : this.classe;
    }
  },
  methods: {
    // ---- Navigation ----
    goToStep: function (targetStep) {
      // Only allow going back to completed steps (step > targetStep)
      // and never allow going forward via stepper click
      if (this.step > targetStep) {
        this.feedback = '';
        this.step = targetStep;
      }
    },

    // ---- Stepper Classes ----
    stepperClass: function (stepIndex) {
      // Step map: 0=Type, 1=Fichier, 3=Infos, 4=Envoi
      if (this.step > stepIndex) {
        return { completed: true };
      }
      if (stepIndex === 0 && this.step === 0) { return { active: true }; }
      if (stepIndex === 1 && this.step === 1) { return { active: true }; }
      if (stepIndex === 2 && this.step === 2) { return { active: true }; }
      if (stepIndex === 3 && this.step === 3) { return { active: true }; }
      return {};
    },

    // ---- CSRF Token ----
    loadCsrfToken: function () {
      let meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) {
        this.csrfToken = meta.getAttribute('content');
      }
    },

    // ---- Data Fetching ----
    fetchClasses: function () {
      return fetch('classes.json')
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Impossible de charger les classes.');
          }
          return response.json();
        })
        .then(function (data) {
          this.classes = data;
          this.nomsClasses = Object.keys(data);
          this.nomsClasses.forEach(function (nomClasse) {
            this.classes[nomClasse].sort();
          }, this);
        }.bind(this))
        .catch(function (err) {
          this.showToast('Erreur lors du chargement des classes : ' + err.message, 'error');
        }.bind(this));
    },

    // ---- Session Persistence (localStorage) ----
    STORAGE_KEY: 'upload_session',

    saveSession: function () {
      try {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify({
          classe: this.classe,
          poste: this.poste,
          classCustom: this.classCustom
        }));
      } catch (e) {
        // localStorage may be unavailable
      }
    },

    restoreSession: function () {
      try {
        let saved = localStorage.getItem(this.STORAGE_KEY) || '{}';
        let data = JSON.parse(saved);
        if (data.classe) this.classe = data.classe;
        if (data.poste) this.poste = data.poste;
        if (data.classCustom) this.classCustom = data.classCustom;
      } catch (e) {
        // ignore
      }
    },

    clearSession: function () {
      try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) { }
    },

    // ---- ZIP Generation ----
    generateReadme: function () {
      return this.poste + '\n' +
        this.effectiveClasse + '\n' +
        new Date().toLocaleString('fr-fr');
    },

    zipFiles: function (filesField) {
      let files = [];
      let filesList = filesField.files;
      for (let i = 0; i < filesList.length; i++) {
        files.push(filesList[i]);
      }

      let totalSize = files.reduce(function (sum, file) { return sum + file.size; }, 0);
      if (totalSize > this.maxSizeBytes) {
        this.showToast('Le dossier/fichier est trop volumineux. Taille max: 50 Mo.', 'error');
        this.hideLoading();
        return null;
      }

      if (files.length > 1000) {
        this.showToast('Trop de fichiers. Maximum: 1000 fichiers.', 'error');
        this.hideLoading();
        return null;
      }

      let zip = new JSZip();
      zip.file('readme.txt', this.generateReadme());
      files.forEach(function (file) {
        let fileName = (file.webkitRelativePath !== '') ? file.webkitRelativePath : file.name;
        zip.file(fileName, file);
      }, this);
      return zip.generateAsync({
        type: 'blob',
        compression: 'DEFLATE',
        compressionOptions: { level: 6 }
      });
    },

    zipCode: function (code) {
      if (code.length > 100000) {
        this.showToast('Le code est trop long. Maximum: 100 000 caractères.', 'error');
        this.hideLoading();
        return null;
      }
      let zip = new JSZip();
      zip.file('readme.txt', this.generateReadme());
      zip.file('code.txt', code);
      return zip.generateAsync({
        type: 'blob',
        compression: 'DEFLATE',
        compressionOptions: { level: 6 }
      });
    },

    prepareZipFile: function () {
      let zip = null;
      if (this.typeData === 'dossier' || this.typeData === 'fichier') {
        if (this.elem.files.length === 0) {
          this.showToast('Sélectionner le fichier/dossier à soumettre !', 'error');
          return null;
        }
        zip = this.zipFiles(this.elem);
      } else {
        if (this.elem.value.length === 0) {
          this.showToast('Veuillez copier/coller votre code !', 'error');
          return null;
        }
        zip = this.zipCode(this.elem.value);
      }
      return zip;
    },

    // ---- Toast System ----
    showToast: function (message, type) {
      type = type || 'info';
      let container = document.getElementById('toast-container');
      if (!container) return;

      let icons = {
        success: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
        error: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
        info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
      };

      let toast = document.createElement('div');
      toast.className = 'toast toast-' + type;
      toast.innerHTML =
        '<span class="toast-icon">' + (icons[type] || icons.info) + '</span>' +
        '<span>' + this.escapeHtml(message) + '</span>' +
        '<button class="toast-close" onclick="this.closest(\'.toast\').classList.add(\'toast-leaving\'); setTimeout(function(e){e.remove()}, 300, this.closest(\'.toast\'))">' +
        '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>' +
        '</button>';

      container.appendChild(toast);

      // Auto-remove after 5 seconds
      setTimeout(function () {
        toast.classList.add('toast-leaving');
        setTimeout(function () { if (toast.parentNode) toast.remove(); }, 300);
      }, 5000);
    },

    escapeHtml: function (text) {
      let div = document.createElement('div');
      div.appendChild(document.createTextNode(text));
      return div.innerHTML;
    },

    // ---- Confetti ----
    showConfetti: function () {
      let container = document.getElementById('confetti-container');
      if (!container) return;
      container.classList.remove('d-none');

      let colors = ['#0870b9', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'];
      let pieces = 60;

      for (let i = 0; i < pieces; i++) {
        let piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        piece.style.width = (Math.random() * 8 + 4) + 'px';
        piece.style.height = (Math.random() * 8 + 4) + 'px';
        piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
        piece.style.animationDuration = (Math.random() * 2 + 1.5) + 's';
        piece.style.animationDelay = (Math.random() * 0.8) + 's';
        container.appendChild(piece);
      }

      // Clean up after animation
      setTimeout(function () {
        container.innerHTML = '';
        container.classList.add('d-none');
      }, 4000);
    },

    // ---- Loading ----
    showLoading: function (msg) {
      let loadingText = document.getElementById('loading-text');
      let loadingOverlay = document.getElementById('loading-overlay');
      if (loadingText) loadingText.textContent = msg || 'Préparation de l\'envoi...';
      if (loadingOverlay) loadingOverlay.classList.remove('d-none');
    },

    hideLoading: function () {
      let loadingOverlay = document.getElementById('loading-overlay');
      if (loadingOverlay) loadingOverlay.classList.add('d-none');
    },

    // ---- Upload ----
    sendZipFile: function () {
      let self = this;
      let formData = new FormData();
      let url = 'upload.php';

      self.uploading = true;
      self.showLoading('Compression et envoi en cours...');

      let zipPromise = self.prepareZipFile();
      if (zipPromise === null) {
        self.uploading = false;
        self.hideLoading();
        return;
      }

      zipPromise
        .then(function (content) {
          formData.append('files', content, 'upload.zip');
          formData.append('classe', self.effectiveClasse);
          formData.append('poste', self.poste);
          formData.append('upload', 'upload');
          formData.append('csrf_token', self.csrfToken);

          return fetch(url, {
            method: 'POST',
            body: formData
          });
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.error) {
            self.showToast(data.error, 'error');
          } else if (data.success) {
            self.showToast(data.success, 'success');
            self.showConfetti();
            document.querySelector('#form').reset();
            self.selectedFilesInfos = [];
            self.elem = null;
            self.step = 0;
          }
        })
        .catch(function (err) {
          self.showToast('Erreur réseau : ' + err.message, 'error');
        })
        .finally(function () {
          self.uploading = false;
          self.hideLoading();
        });
    },

    // ---- Drag & Drop (from Windows Explorer) ----
    onDragOver: function (e) {
      e.preventDefault();
      let overlay = document.getElementById('drop-overlay');
      if (overlay) overlay.classList.remove('d-none');

      // Only attach the dragleave handler once to avoid memory leaks
      if (!this._dragLeaveAttached) {
        this._dragLeaveAttached = true;
        this._onDragLeave = function handler(e) {
          if (e.clientX <= 0 || e.clientY <= 0 || e.clientX >= window.innerWidth || e.clientY >= window.innerHeight) {
            if (overlay) overlay.classList.add('d-none');
          }
        };
        document.body.addEventListener('dragleave', this._onDragLeave);
      }
    },

    // ---- Recursively walk directory entries to collect files with webkitRelativePath ----
    walkEntry: function (entry, path, results, done) {
      let self = this;
      // path is the relative path from the root dropped folder
      if (entry.isFile) {
        entry.file(function (file) {
          // Preserve the relative path by adding webkitRelativePath
          try {
            Object.defineProperty(file, 'webkitRelativePath', {
              value: path + file.name,
              writable: false,
              configurable: true
            });
          } catch (e) {
            // Fallback if Object.defineProperty not supported on File
          }
          results.push(file);
          done();
        }, done);
      } else if (entry.isDirectory) {
        let dirReader = entry.createReader();
        let entries = [];
        let readEntries = function () {
          dirReader.readEntries(function (batch) {
            if (batch.length === 0) {
              // All entries read, process them
              let pending = entries.length;
              if (pending === 0) {
                done();
              } else {
                entries.forEach(function (childEntry) {
                  self.walkEntry(childEntry, path + entry.name + '/', results, function () {
                    pending--;
                    if (pending === 0) done();
                  });
                });
              }
            } else {
              entries = entries.concat(Array.prototype.slice.call(batch));
              readEntries();
            }
          }, done);
        };
        readEntries();
      } else {
        done();
      }
    },

    onDrop: function (e) {
      e.preventDefault();
      let overlay = document.getElementById('drop-overlay');
      if (overlay) overlay.classList.add('d-none');

      let self = this;
      let items = e.dataTransfer.items;
      if (!items || items.length === 0) return;

      // Detect if we have directories
      let hasDirectories = false;
      let entries = [];
      for (let i = 0; i < items.length; i++) {
        let item = items[i];
        if (item.webkitGetAsEntry) {
          let entry = item.webkitGetAsEntry();
          if (entry) {
            entries.push(entry);
            if (entry.isDirectory) hasDirectories = true;
          }
        }
      }

      if (entries.length === 0) {
        // Fallback: use dataTransfer.files (flat, no directory structure)
        let flatFiles = e.dataTransfer.files;
        if (!flatFiles || flatFiles.length === 0) return;
        self.typeData = 'fichier';
        let flatInfos = [];
        for (let fi = 0; fi < flatFiles.length; fi++) {
          let f = flatFiles[fi];
          flatInfos.push({ name: f.name, size: f.size });
        }
        self.selectedFilesInfos = flatInfos;
        self.elem = { files: flatFiles };
        self.files = flatFiles;
        self.step = 2;
        return;
      }

      self.typeData = hasDirectories ? 'dossier' : 'fichier';

      // Walk all entries recursively to collect files with proper paths
      let allFiles = [];
      let pendingEntries = entries.length;

      entries.forEach(function (entry) {
        self.walkEntry(entry, '', allFiles, function () {
          pendingEntries--;
          if (pendingEntries === 0) {
            // All entries processed
            // Build a FileList-like object
            let dataTransfer = new DataTransfer();
            allFiles.forEach(function (f) {
              try {
                dataTransfer.items.add(f);
              } catch (e) {
                // Some browsers may not support DataTransfer.items.add for File objects
              }
            });
            let fileList = dataTransfer.files;

            // Build file info for display
            let fileInfos = [];
            for (let j = 0; j < allFiles.length; j++) {
              let file = allFiles[j];
              fileInfos.push({
                name: file.webkitRelativePath || file.name,
                size: file.size
              });
            }

            self.selectedFilesInfos = fileInfos;
            self.elem = { files: fileList };
            self.files = fileList;

            // Jump to INFO step (step 2)
            self.step = 2;
          }
        });
      });
    },

    formatFileSize: function (bytes) {
      if (bytes < 1024) return bytes + ' o';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
      return (bytes / 1048576).toFixed(1) + ' Mo';
    },

    // ---- Event Handlers ----
    onPreviousStep: function () {
      if (this.step === 3) {
        // From confirmation, go back to infos (step 2)
        this.step = 2;
      } else if (this.step === 2) {
        // From infos, go back to file selection (step 1)
        this.step = 1;
      } else if (this.step === 1) {
        // From file selection, go back to type selection (step 0)
        this.step = 0;
      }
    },

    onTypeDataClicked: function (typeData) {
      this.typeData = typeData;
      this.selectedFilesInfos = [];
      this.code = '';
      this.step = 1;
    },

    onDataChanged: function (elemId) {
      this.elem = document.querySelector(elemId);
      let files = this.elem.files;

      this.selectedFilesInfos = [];
      if (elemId === '#files' || elemId === '#fichier') {
        this.files = files;
        // Build file info preview for each selected file
        for (let i = 0; i < files.length; i++) {
          let file = files[i];
          this.selectedFilesInfos.push({
            name: file.webkitRelativePath || file.name,
            size: file.size
          });
        }
      } else if (elemId === '#code') {
        this.code = this.elem.value;
        this.selectedFilesInfos.push({
          name: 'Code saisi',
          size: this.elem.value.length
        });
      }

      this.step = 2;
    },

    onClasseChanged: function () {
      if (this.classe !== '') {
        this.saveSession();
      }
      if (this.effectiveClasse && this.poste) {
        this.step = 4;
      } else if (this.effectiveClasse) {
        this.step = 3;
      } else {
        this.step = 2;
      }
    },

    onPosteChanged: function () {
      if (this.poste !== '') {
        this.saveSession();
      }
      if (this.effectiveClasse && this.poste) {
        this.step = 4;
      } else if (this.effectiveClasse) {
        this.step = 3;
      } else {
        this.step = 2;
      }
    },

    onNextStep: function () {
      if (this.isValidInformations(this.step)) {
        if (this.step === 1) {
          this.step = 2;
        } else if (this.step === 2) {
          this.step = 3;
        } else if (this.step === 3) {
          this.step = 4;
        }
      }
    },

    isValidInformations: function (step) {
      if (step === 3) {
        let effectiveClass = this.classe === 'Autre' ? this.classeCustom : this.classe;
        return effectiveClass !== '' && this.poste !== '';
      }
      return true;
    },

    onUploadClicked: function () {
      this.sendZipFile();
    }
  }
});