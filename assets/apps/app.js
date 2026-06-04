const app = new Vue({
  el: '#app',
  data: {
    step: 0,
    typeData: '',
    elem: null,
    classe: '',
    poste: 'Poste 1',
    // nomPrenom: '',
    nomsClasses: [],
    classes: {},
    feedbackClass: '',
    feedback: '',
    uploading: false,

    // File size limit: 50 MB for the uncompressed data
    // (JSZip loads all files in memory)
    maxSizeBytes: 50 * 1024 * 1024
  },
  mounted: function () {
    this.fetchClasses();
    this.restoreSession();
  },
  methods: {
    // ---- Data Fetching ----
    fetchClasses: function () {
      return fetch('classes.json')
        .then(function (response) { return response.json(); })
        .then(function (data) {
          this.classes = data;
          this.nomsClasses = Object.keys(data);
          this.nomsClasses.forEach(function (nomClasse) {
            this.classes[nomClasse].sort();
          }, this);
        }.bind(this));
    },

    // ---- Session Persistence (localStorage) ----
    STORAGE_KEY: 'upload_session',

    saveSession: function () {
      try {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify({
          classe: this.classe,
          poste: this.poste
        }));
      } catch (e) {
        // localStorage may be unavailable (private browsing, quota, etc.)
      }
    },

    restoreSession: function () {
      try {
        var saved = localStorage.getItem(this.STORAGE_KEY) || '{}';
        var data = JSON.parse(saved);
        if (data.classe) {
          this.classe = data.classe;
        }
        if (data.poste) {
          this.poste = data.poste;
        }
      } catch (e) {
        // ignore
      }
    },

    clearSession: function () {
      try {
        localStorage.removeItem(this.STORAGE_KEY);
      } catch (e) {
        // ignore
      }
    },

    // ---- ZIP Generation ----
    generateReadme: function () {
      return this.poste + '\n' +
        this.classe + '\n' +
        new Date().toLocaleString('fr-fr');
    },

    zipFiles: function (filesField) {
      var files = [];
      var filesList = filesField.files;
      for (var i = 0; i < filesList.length; i++) {
        files.push(filesList[i]);
      }

      // Check total uncompressed size before zipping
      var totalSize = files.reduce(function (sum, file) { return sum + file.size; }, 0);
      if (totalSize > this.maxSizeBytes) {
        this.reportError('Le dossier/fichier est trop volumineux. Taille max: 50 Mo.');
        this.hideLoading();
        return null;
      }

      if (files.length > 1000) {
        this.reportError('Trop de fichiers. Maximum: 1000 fichiers.');
        this.hideLoading();
        return null;
      }

      var zip = new JSZip();
      zip.file('readme.txt', this.generateReadme());
      files.forEach(function (file) {
        var fileName = (file.webkitRelativePath !== '') ? file.webkitRelativePath : file.name;
        zip.file(fileName, file);
      }, this);
      // Compression DEFLATE niveau 6 (bon équilibre taille/vitesse)
      return zip.generateAsync({
        type: 'blob',
        compression: 'DEFLATE',
        compressionOptions: { level: 6 }
      });
    },

    zipCode: function (code) {
      if (code.length > 100000) {
        this.reportError('Le code est trop long. Maximum: 100 000 caractères.');
        this.hideLoading();
        return null;
      }
      var zip = new JSZip();
      zip.file('readme.txt', this.generateReadme());
      zip.file('code.txt', code);
      // Compression DEFLATE niveau 6 (bon équilibre taille/vitesse)
      return zip.generateAsync({
        type: 'blob',
        compression: 'DEFLATE',
        compressionOptions: { level: 6 }
      });
    },

    prepareZipFile: function () {
      var zip = null;
      if (this.typeData === 'dossier') {
        if (this.elem.files.length === 0) {
          this.reportError('Sélectionner le dossier à soumettre !');
          return null;
        }
        zip = this.zipFiles(this.elem);
      } else if (this.typeData === 'fichier') {
        if (this.elem.files.length === 0) {
          this.reportError('Sélectionner le fichier à soumettre !');
          return null;
        }
        zip = this.zipFiles(this.elem);
      } else {
        if (this.elem.value.length === 0) {
          this.reportError('Veuillez copier/coller votre code !');
          return null;
        }
        zip = this.zipCode(this.elem.value);
      }
      return zip;
    },

    // ---- UI Helpers ----
    showLoading: function (msg) {
      document.getElementById('loading-text').textContent = msg || 'Préparation de l\'envoi...';
      document.getElementById('loading-overlay').classList.remove('d-none');
    },

    hideLoading: function () {
      document.getElementById('loading-overlay').classList.add('d-none');
    },

    reportFeedback: function (ftype, description) {
      this.feedbackClass = ftype;
      this.feedback = description;
    },

    reportError: function (text) {
      this.reportFeedback('bg-danger', text);
    },

    reportSuccess: function (text) {
      this.reportFeedback('bg-success', text);
    },

    // ---- Upload ----
    sendZipFile: function () {
      var self = this;
      var formData = new FormData();
      var url = 'upload.php';

      self.uploading = true;
      self.showLoading('Compression et envoi en cours...');

      var zipPromise = self.prepareZipFile();
      if (zipPromise === null) {
        self.uploading = false;
        self.hideLoading();
        return;
      }

      zipPromise
        .then(function (content) {
          formData.append('files', content, 'upload.zip');
          formData.append('classe', self.classe);
          formData.append('poste', self.poste);
          formData.append('upload', 'upload');

          return fetch(url, {
            method: 'POST',
            body: formData
          });
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.error) {
            self.reportError(data.error);
          } else if (data.success) {
            self.reportSuccess(data.success);
            document.querySelector('#form').reset();
            self.step = 0;
          }
        })
        .catch(function (err) {
          self.reportError('Erreur réseau : ' + err.message);
        })
        .finally(function () {
          self.uploading = false;
          self.hideLoading();
        });
    },

    // ---- Event Handlers ----
    onTypeDataClicked: function (typeData) {
      this.typeData = typeData;
      this.feedback = '';
      this.step = 1;
    },

    onDataChanged: function (elemId) {
      this.elem = document.querySelector(elemId);
      this.step = 2 + (this.classe != '') + (this.classe != '' && this.poste != '');
    },

    onClasseChanged: function () {
      if (this.classe !== '') {
        this.saveSession();
      }
      this.step = 2 + (this.classe != '') + (this.classe != '' && this.poste != '');
    },

    onPosteChanged: function () {
      if (this.poste !== '') {
        this.saveSession();
      }
      this.step = 2 + (this.classe != '') + (this.classe != '' && this.poste != '');
    },

    onUploadClicked: function () {
      this.sendZipFile();
    }
  }
});