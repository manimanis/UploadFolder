new Vue({
  el: '#admin-app',
  data: {
    authenticated: false,
    loading: false,
    deleting: false,
    password: '',
    loginError: '',
    searchQuery: '',
    activeTab: 'tree',
    sortBy: 'date',
    sortDir: 'desc',
    darkMode: false,
    selectedFiles: [],
    autoRefresh: false,
    autoRefreshInterval: null,
    showDetails: null,
    checkedPaths: {},
    stats: {
      nbClasses: 0,
      nbDates: 0,
      total_files: 0,
      total_size: 0,
      totalSizeFormatted: '0 o',
      classes: {}
    },
    dates: {},
    logLines: [],
    toast: { show: false, message: '', type: 'success' },
    meta: {
      noms: [],
      matieres: [],
      enseignants: [],
      classes: []
    },
    exams: [],
    examForm: {
      id: '',
      name: '',
      date: new Date().toISOString().slice(0, 10),
      time_start: '08:00',
      time_end: '12:00',
      subject: '',
      teacher: '',
      classes: ''
    },
    showForm: false,
    lang: I18n ? I18n.currentLang : 'fr',
    selectedTeacher: '',
    selectedClass: ''
  },
  computed: {
    filteredDates: function () {
      let query = this.searchQuery.toLowerCase().trim();
      let selectedClass = this.selectedClass;
      let result = {};
      let dates = this.dates;

      let sortedKeys = Object.keys(dates).sort();
      if (this.sortBy === 'date' && this.sortDir === 'desc') sortedKeys.reverse();

      for (let i = 0; i < sortedKeys.length; i++) {
        let dateName = sortedKeys[i];
        let dateEntry = dates[dateName];
        let filteredClasses = {};

        let classKeys = Object.keys(dateEntry.classes);
        for (let j = 0; j < classKeys.length; j++) {
          let className = classKeys[j];

          // Filter by selected class
          if (selectedClass && className !== selectedClass) continue;

          let postes = dateEntry.classes[className];
          let filteredPostes = {};

          let posteKeys = Object.keys(postes);
          for (let k = 0; k < posteKeys.length; k++) {
            let posteName = posteKeys[k];
            let posteData = postes[posteName];
            let filteredFiles = [];

            for (let f = 0; f < posteData.fileList.length; f++) {
              let file = posteData.fileList[f];
              if (!query ||
                  file.name.toLowerCase().indexOf(query) !== -1 ||
                  className.toLowerCase().indexOf(query) !== -1 ||
                  posteName.toLowerCase().indexOf(query) !== -1 ||
                  dateName.indexOf(query) !== -1) {
                filteredFiles.push(file);
              }
            }

            if (filteredFiles.length > 0 || !query) {
              filteredPostes[posteName] = {
                size: posteData.size,
                sizeFormatted: posteData.sizeFormatted,
                files: posteData.files,
                fileList: query ? filteredFiles : posteData.fileList,
                relativePath: posteData.relativePath
              };
            }
          }

          if (Object.keys(filteredPostes).length > 0) {
            filteredClasses[className] = filteredPostes;
          }
        }

        if (Object.keys(filteredClasses).length > 0) {
          let totalFiles = 0;
          let totalSize = 0;
          Object.values(filteredClasses).forEach(function (postes) {
            Object.values(postes).forEach(function (p) {
              totalFiles += p.files;
              totalSize += p.size;
            });
          });
          result[dateName] = {
            classes: filteredClasses,
            totalFiles: totalFiles,
            totalSize: totalSize,
            totalSizeFormatted: this.formatSize(totalSize)
          };
        }
      }
      return result;
    },
    classChart: function () {
      let classes = this.stats.classes;
      let keys = Object.keys(classes);
      if (keys.length === 0) return [];
      let max = 0;
      keys.forEach(function (k) {
        if (classes[k].files > max) max = classes[k].files;
      });
      let chart = [];
      keys.sort(function (a, b) { return classes[b].files - classes[a].files; });
      keys.forEach(function (k) {
        chart.push({
          name: k,
          files: classes[k].files,
          size: classes[k].sizeFormatted,
          percent: max > 0 ? (classes[k].files / max * 100) : 0
        });
      });
      return chart;
    },
    allFiles: function () {
      let files = [];
      let dates = this.dates;
      Object.keys(dates).forEach(function (dateName) {
        let classes = dates[dateName].classes;
        Object.keys(classes).forEach(function (className) {
          let postes = classes[className];
          Object.keys(postes).forEach(function (posteName) {
            let poste = postes[posteName];
            poste.fileList.forEach(function (file) {
              files.push(file);
            });
          });
        });
      });
      return files;
    },
    selectedCount: function () {
      let self = this;
      let count = 0;
      this.allFiles.forEach(function (f) {
        if (self.checkedPaths[f.relativePath]) count++;
      });
      return count;
    },
    filteredExams: function () {
      return this.exams.filter((exam) => {
        return this.selectedTeacher ? exam.teacher === this.selectedTeacher : true;
      });
    },
    t: function () {
      var lang = this.lang;
      return function (key) {
        return I18n ? I18n.t(key) : key;
      };
    }
  },
  watch: {
    activeTab: function (tab) {
      this.saveConfig();
      if (tab === 'exams') this.initExamsTab();
      if (tab === 'log') this.loadLog();
    }
  },
  mounted: function () {
    this.loadConfig();
    this.checkAuth();
  },
  methods: {
    loadConfig: function () {
      var tab = localStorage.getItem('admin_activeTab');
      if (tab) this.activeTab = tab;
      this.darkMode = localStorage.getItem('admin_darkMode') === 'true';
      if (this.darkMode) document.documentElement.setAttribute('data-theme', 'dark');
    },

    saveConfig: function () {
      localStorage.setItem('admin_activeTab', this.activeTab);
      localStorage.setItem('admin_darkMode', this.darkMode ? 'true' : 'false');
    },

    api: function (action, options) {
      let url = 'api_admin.php?action=' + encodeURIComponent(action);
      if (options && options.params) {
        for (let key in options.params) {
          url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(options.params[key]);
        }
      }
      let fetchOptions = { method: 'GET' };
      if (options && options.body) {
        fetchOptions.method = 'POST';
        fetchOptions.body = options.body;
      }
      return fetch(url, fetchOptions).then(function (r) { return r.json(); });
    },

    showToast: function (message, type) {
      let self = this;
      self.toast = { show: true, message: message, type: type || 'success' };
      setTimeout(function () { self.toast.show = false; }, 3000);
    },

    toggleDarkMode: function () {
      this.darkMode = !this.darkMode;
      this.saveConfig();
      if (this.darkMode) {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
    },

    toggleAutoRefresh: function () {
      let self = this;
      self.autoRefresh = !self.autoRefresh;
      if (self.autoRefresh) {
        self.autoRefreshInterval = setInterval(function () {
          self.loadData();
        }, 30000);
        self.showToast('Rafraîchissement automatique activé (30s)', 'info');
      } else {
        clearInterval(self.autoRefreshInterval);
        self.autoRefreshInterval = null;
        self.showToast('Rafraîchissement automatique désactivé', 'info');
      }
    },

    checkAuth: function () {
      let self = this;
      self.loading = true;
      self.api('auth_status').then(function (data) {
        self.authenticated = data.authenticated;
        if (self.authenticated) {
          self.loadData();
        } else {
          self.loading = false;
        }
      });
    },

    login: function () {
      let self = this;
      self.loginError = '';
      self.loading = true;
      let body = new FormData();
      body.append('password', self.password);
      self.api('login', { body: body }).then(function (data) {
        if (data.error) {
          self.loginError = data.error;
          self.loading = false;
        } else {
          self.authenticated = true;
          self.loadData();
        }
      });
    },

    logout: function () {
      let self = this;
      self.api('logout').then(function () {
        self.authenticated = false;
        self.password = '';
        if (self.autoRefreshInterval) clearInterval(self.autoRefreshInterval);
      });
    },

    loadExams: function () {
      let self = this;
      self.api('exams_list').then(function (data) {
        if (data.error) {
          self.showToast(data.error, 'error');
          return;
        }
        self.exams = data.exams || [];
        self.meta = {
          noms: data.noms || [],
          matieres: data.matieres || [],
          enseignants: data.enseignants || [],
          classes: data.classes || []
        };
      });
    },

    saveExam: function () {
      let self = this;
      let body = new FormData();
      body.append('id', self.examForm.id);
      body.append('name', self.examForm.name);
      body.append('date', self.examForm.date);
      body.append('time_start', self.examForm.time_start);
      body.append('time_end', self.examForm.time_end);
      body.append('subject', self.examForm.subject);
      body.append('teacher', self.examForm.teacher);
      body.append('classes', self.examForm.classes);

      self.api('exams_save', { body: body }).then(function (data) {
        if (data.error) {
          self.showToast(data.error, 'error');
          return;
        }
        self.showToast(self.examForm.id ? 'Examen modifié.' : 'Examen ajouté.', 'success');
        self.showForm = false;
        self.resetExamForm();
        self.loadExams();
      });
    },

    showAddForm: function () {
      this.resetExamForm();
      this.showForm = true;
    },

    cancelForm: function () {
      this.showForm = false;
      this.resetExamForm();
    },

    editExam: function (exam) {
      this.examForm = {
        id: exam.id,
        name: exam.name,
        date: exam.date,
        time_start: exam.time_start,
        time_end: exam.time_end,
        subject: exam.subject,
        teacher: exam.teacher,
        classes: (exam.classes || []).join(', ')
      };
      this.showForm = true;
    },

    deleteExam: function (exam) {
      let self = this;
      if (!confirm('Supprimer l\'examen « ' + exam.name + ' » ?')) return;
      let body = new FormData();
      body.append('id', exam.id);
      self.api('exams_delete', { body: body }).then(function (data) {
        if (data.error) {
          self.showToast(data.error, 'error');
          return;
        }
        self.showToast('Examen supprimé.', 'success');
        if (self.examForm.id === exam.id) {
          self.resetExamForm();
        }
        self.loadExams();
      });
    },

    resetExamForm: function () {
      this.examForm = {
        id: '',
        name: '',
        date: new Date().toISOString().slice(0, 10),
        time_start: '08:00',
        time_end: '12:00',
        subject: '',
        teacher: '',
        classes: ''
      };
    },

    selectEnseignant: function (enseignant) {
      this.selectedTeacher = enseignant;
    },

    getExamsByTeacher: function (enseignant) {
      if (this.selectedTeacher && this.selectedTeacher !== enseignant) return [];
      return this.exams.filter(function (exam) {
        return exam.teacher === enseignant;
      });
    },

    loadData: function () {
      let self = this;
      self.loading = true;
      self.api('data').then(function (data) {
        if (data.error) {
          self.showToast(data.error, 'error');
          self.authenticated = false;
          self.loading = false;
          return;
        }
        self.stats = {
          nbClasses: Object.keys(data.stats.classes).length,
          nbDates: Object.keys(data.dates).length,
          total_files: data.stats.total_files,
          total_size: data.stats.total_size,
          totalSizeFormatted: data.stats.totalSizeFormatted,
          classes: data.stats.classes
        };
        self.dates = data.dates;
        self.checkedPaths = {};
        self.loading = false;
      });
    },

    loadLog: function () {
      let self = this;
      self.api('log').then(function (data) {
        self.logLines = data.log || [];
      });
    },

    toggleAllFiles: function (event) {
      let checked = event.target.checked;
      let self = this;
      let files = this.allFiles;
      if (checked) {
        files.forEach(function (f) { self.$set(self.checkedPaths, f.relativePath, true); });
      } else {
        files.forEach(function (f) { self.$delete(self.checkedPaths, f.relativePath); });
      }
    },

    toggleFile: function (event, file) {
      if (event.target.checked) {
        this.$set(this.checkedPaths, file.relativePath, true);
      } else {
        this.$delete(this.checkedPaths, file.relativePath);
      }
    },

    toggleClassFiles: function (event, className, dateName) {
      let checked = event.target.checked;
      let self = this;
      let classes = this.dates[dateName].classes;
      if (!classes[className]) return;
      let posteData = classes[className];
      Object.keys(posteData).forEach(function (posteName) {
        let fileList = posteData[posteName].fileList;
        fileList.forEach(function (file) {
          if (checked) {
            self.$set(self.checkedPaths, file.relativePath, true);
          } else {
            self.$delete(self.checkedPaths, file.relativePath);
          }
        });
      });
    },

    togglePosteFiles: function (event, dateName, className, posteName) {
      let checked = event.target.checked;
      let self = this;
      let poste = this.dates[dateName].classes[className][posteName];
      if (!poste) return;
      poste.fileList.forEach(function (file) {
        if (checked) {
          self.$set(self.checkedPaths, file.relativePath, true);
        } else {
          self.$delete(self.checkedPaths, file.relativePath);
        }
      });
    },

    isChecked: function (relativePath) {
      return !!this.checkedPaths[relativePath];
    },

    downloadSelected: function () {
      let self = this;
      let files = self.allFiles.filter(function (f) { return self.checkedPaths[f.relativePath]; });
      if (files.length === 0) {
        self.showToast('Aucun fichier sélectionné.', 'info');
        return;
      }
      let paths = files.map(function (f) { return f.relativePath; });
      let body = new FormData();
      body.append('paths', JSON.stringify(paths));
      self.deleting = true;
      fetch('api_admin.php?action=download_multiple', {
        method: 'POST',
        body: body
      }).then(function (response) {
        if (!response.ok) throw new Error('Erreur téléchargement');
        return response.blob();
      }).then(function (blob) {
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'selection.zip';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        self.deleting = false;
      }).catch(function (err) {
        self.showToast(err.message, 'error');
        self.deleting = false;
      });
    },

    downloadFile: function (relativePath) {
      window.location.href = 'api_admin.php?action=download&path=' + encodeURIComponent(relativePath);
    },

    openClassFolder: function (dateName='', className='', posteName='') {
      fetch('api_admin.php?action=open_folder&date=' + encodeURIComponent(dateName) + '&class=' + encodeURIComponent(className) + '&poste=' + encodeURIComponent(posteName))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) {
            this.showToast(data.error, 'error');
          } else if (data.success) {
            this.showToast('Dossier ouvert dans l\'explorateur.', 'success');
          }
        }.bind(this));
    },

    downloadClass: function (className) {
      window.location.href = 'api_admin.php?action=download_class&class=' + encodeURIComponent(className);
    },

    exportCsv: function () {
      window.location.href = 'api_admin.php?action=export_csv';
    },

    showFileDetails: function (file) {
      this.showDetails = file;
    },

    closeDetails: function () {
      this.showDetails = null;
    },

    formatSize: function (bytes) {
      if (bytes < 1024) return bytes + ' o';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
      if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' Mo';
      return (bytes / 1073741824).toFixed(2) + ' Go';
    },

    parseIpFromFilename: function (name) {
      let parts = name.replace('.zip', '').split('_');
      if (parts.length >= 2) {
        return parts.slice(1).join('_').replace(/-/g, '.');
      }
      return '—';
    },

    setLang: function (lang) {
      this.lang = lang;
      if (I18n) I18n.setLang(lang);
      this.$forceUpdate();
    },


    initExamsTab: function () {
      this.loadExams();
    }
  }
});