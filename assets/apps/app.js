$(() => {
  const form = $('#form');
  const nomPrenom = $('#nom_prenom');
  const classe = $('#classe');
  const feedback = $('#feedback');

  const dossier = document.querySelector('#type-dossier');
  const fichier = document.querySelector('#type-fichier');
  const code = document.querySelector('#type-code');
  const radioSelectModes = [dossier, fichier, code];
  const radioSelectDivs = [
    document.querySelector('#dossier-select'),
    document.querySelector('#fichier-select'),
    document.querySelector('#code-select'),
  ];

  const getSelectedModeIndex = function () {
    return radioSelectModes.findIndex(radio => radio.checked);
  };
  const setSelectedModeIndex = function (idx) {
    selectedModeIndex = idx;
    selectedMode = radioSelectModes[idx].value;
    radioSelectDivs.forEach((div, index) => {
      div.style.display = (index == idx) ? 'block' : 'none';
    });
  };

  let selectedModeIndex = 0;
  setSelectedModeIndex(selectedModeIndex);
  radioSelectModes.forEach(radio => radio.addEventListener('click', () => {
    const idx = getSelectedModeIndex();
    setSelectedModeIndex(idx);
  }));

  const zipFiles = (filesField) => {
    const files = [];
    const filesList = filesField.files;
    for (let i = 0; i < filesList.length; i++) {
      files.push(filesList[i]);
    }

    var zip = new JSZip();
    files.forEach((file) => {
      const fileName = (file.webkitRelativePath != '') ? file.webkitRelativePath : file.name;
      zip.file(fileName, file);
    });
    return zip.generateAsync({ type: 'blob' });
  };

  const zipCode = (text) => {
    var zip = new JSZip();
    zip.file("code.txt", text);
    return zip.generateAsync({ type: 'blob' });
  };

  const reportError = (text) => {
    feedback.removeClass('badge bg-success bg-danger');
    feedback.addClass('badge bg-danger');
    feedback.text("Erreur : " + text);
  };

  const reportSuccess = (text) => {
    feedback.removeClass('badge bg-success bg-danger');
    feedback.addClass('badge bg-success');
    feedback.text("Ok : " + text);
  };

  form.submit((e) => {
    e.preventDefault();

    let zip = null;
    if (selectedMode == 'dossier') {
      if (document.f.files.files.length == 0) {
        reportError('Sélectionner le dossier à soumettre !');
        return;
      }
      zip = zipFiles(document.f.files);
    } else if (selectedMode == 'fichier') {
      if (document.f.fichier.files.length == 0) {
        reportError('Sélectionner le fichier à soumettre !');
        return;
      }
      zip = zipFiles(document.f.fichier);
    } else {
      if (document.f.code.value.length == 0) {
        reportError('Veuillez copier/coller votre code !');
        return;
      }
      zip = zipCode(document.f.code.value);
    }

    const formData = new FormData();
    const url = 'upload.php';
    zip.then(function (content) {
      formData.append("files", content);
      formData.append("nom_prenom", nomPrenom.val());
      formData.append("classe", classe.val())
      formData.append("upload", "upload");

      fetch(url, {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            reportError(data.error);
          } else if (data.success) {
            reportSuccess(data.success);
            document.f.reset();
          }
        });
    });

  });
});