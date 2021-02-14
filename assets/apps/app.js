const app = new Vue({
  el: '#app',
  data: {
    step: 0,
    typeData: '',
    elem: null,
    classe: '',
    nomPrenom: '',
    selectedCode: "",
    selectedFile: "",
    selectedDir: [],
    nomsClasses: [],
    classes: {},
    feedbackClass: "",
    title: "",
    feedback: ""
  },
  mounted: function () {
    this.fetchClasses();
  },
  methods: {
    fetchClasses: function () {
      return fetch('classes.json')
        .then(response => response.json())
        .then(data => {
          this.classes = data;
          this.nomsClasses = Object.keys(data);
        });
    },
    generateReadme: function () {
      return `${this.nomPrenom}
${this.classe}
${new Date().toLocaleString('Fr-fr')}`;
    },
    zipFiles: function (filesField) {
      const files = [];
      const filesList = filesField.files;
      for (let i = 0; i < filesList.length; i++) {
        files.push(filesList[i]);
      }

      var zip = new JSZip();
      zip.file("readme.txt", this.generateReadme());
      files.forEach((file) => {
        const fileName = (file.webkitRelativePath != '') ? file.webkitRelativePath : file.name;
        zip.file(fileName, file);
      });
      return zip.generateAsync({ type: 'blob' });
    },
    zipCode: function (code) {
      var zip = new JSZip();
      zip.file("readme.txt", this.generateReadme());
      zip.file("code.txt", code);
      return zip.generateAsync({ type: 'blob' });
    },
    prepareZipFile: function () {
      let zip = null;
      if (this.typeData == 'dossier') {
        if (this.elem.files.length == 0) {
          this.reportError('Sélectionner le dossier à soumettre !');
          return null;
        }
        zip = this.zipFiles(this.elem);
      } else if (this.typeData == 'fichier') {
        if (this.elem.files.length == 0) {
          this.reportError('Sélectionner le fichier à soumettre !');
          return null;
        }
        zip = this.zipFiles(this.elem);
      } else {
        if (this.elem.value.length == 0) {
          this.reportError('Veuillez copier/coller votre code !');
          return null;
        }
        zip = this.zipCode(this.elem.value);
      }
      return zip;
    },
    reportFeedback: function (ftype, title, description) {
      this.feedbackClass = ftype;
      this.feedback = description;
      this.title = title;
    },
    reportError: function (text) {
      this.reportFeedback('bg-danger', 'Erreur', text);
    },
    reportSuccess: function (text) {
      this.reportFeedback('bg-success', 'Ok', text);
    },
    sendZipFile: function () {
      const formData = new FormData();
      const url = 'upload.php';
      this
        .prepareZipFile()
        .then((content) => {
          formData.append("files", content);
          formData.append("nom_prenom", this.nomPrenom);
          formData.append("classe", this.classe);
          formData.append("upload", "upload");

          fetch(url, {
            method: 'POST',
            body: formData
          })
            .then(response => response.json())
            .then(data => {
              // btnUpload.prop('disabled', false);
              if (data.error) {
                this.reportError(data.error);
              } else if (data.success) {
                this.reportSuccess(data.success);
                // document.f.reset();
                document.querySelector('#form').reset();
                this.step = 0;
              }
            })
            .catch(err => {
              // btnUpload.prop('disabled', false);
              this.reportError(err);
            });
        });
    },
    onTypeDataClicked: function (typeData) {
      this.typeData = typeData;
      this.feedback = '';
      this.classe = '';
      this.nomPrenom = '';
      this.step = 1;
    },
    onDataChanged: function (elemId) {
      console.log('onDataChanged()');
      this.elem = document.querySelector(elemId);
      this.classe = '';
      this.step = 2;
    },
    onClasseChanged: function () {
      console.log('onClasseChanged()');
      if (this.classe != '') {
        this.nomPrenom = '';
        this.step = 3;
      }
    },
    onNomPrenomChanged: function () {
      console.log('onNomPrenomChanged()');
      if (this.nomPrenom != '') {
        this.step = 4;
      }
    },
    onUploadClicked: function () {
      this.sendZipFile();
    }
  }
});
