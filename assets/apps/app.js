$(() => {
    const form = $('#form');
    const nomPrenom = $('#nom_prenom');
    const classe = $('#classe');

    form.submit((e) => {
        e.preventDefault();
        const files = [];
        const filesList = document.forms[0].files.files;
        for (let i = 0; i < filesList.length; i++) {
            files.push(filesList[i]);
        }

        var zip = new JSZip();
        files.forEach((file) => {
            zip.file(file.webkitRelativePath, file);
        });

        const formData = new FormData();
        const url = 'upload.php';
        zip.generateAsync({ type: "blob" })
            .then(function (content) {
                formData.append("files", content);
                formData.append("nom_prenom", nomPrenom.val());
                formData.append("classe", classe.val())
                formData.append("upload", "upload");

                fetch(url, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    console.log(response);
                });
            });
        
    });
});