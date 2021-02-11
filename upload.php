<?php

if (!isset($_POST['upload'])) {
    die(json_encode(array(
        "error" => "Erreur!",
        'POST' => $_POST,
        'FILES' => $_FILES
    )));
}

$nom_prenom = $_POST['nom_prenom'];
$classe = $_POST['classe'];
$files = $_FILES['files'];

if ($nom_prenom == '' || $classe == '' || count($files) == 0) {
    die(json_encode(array(
        "error" => "<p>Veuillez indiquer toutes les informations : <p>
<ul>
  <li>Votre nom et prénom</li>
  <li>Votre classe</li>
  <li>Le dossier à télécharger</li>
</ul>",
        'POST' => $_POST,
        'FILES' => $_FILES
    )));
}

$accented_array = array(
    'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A',
    'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
    'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
    'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I',
    'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
    'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
    'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B',
    'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
    'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
    'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i',
    'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
    'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u',
    'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y'
);

$upload_folder = dirname(__FILE__) . "/upload_folder";
if (!is_dir($upload_folder)) {
    mkdir($upload_folder);
}
$upload_folder .= '/' . str_replace(' ', '-', $classe);
if (!is_dir($upload_folder)) {
    mkdir($upload_folder);
}
$upload_folder .= '/' . str_replace(' ', '-', strtr($nom_prenom, $accented_array));
if (!is_dir($upload_folder)) {
    mkdir($upload_folder);
}
$upload_folder .= '/' . date('Ymd');
if (!is_dir($upload_folder)) {
    mkdir($upload_folder);
}
$file = date('His');
$file .= '_' . str_replace('.', '-', $_SERVER['REMOTE_ADDR']);

$upload_dir = $upload_folder . '/' . $file;
if (!is_dir($upload_dir)) {
    mkdir($upload_dir);
}


if (strlen($_FILES['files']['name']) > 1) {
    if (move_uploaded_file($_FILES['files']['tmp_name'], $upload_dir . "/file.zip")) {
        die(json_encode(array("success" => "Folder is successfully uploaded")));
    } else {
        die(json_encode(array(
            "error" => "Could not upload file.",
            'POST' => $_POST,
            'FILES' => $_FILES
        )));
    }
} else {
    die(json_encode(array(
        "error" => "No files to upload.",
        'POST' => $_POST,
        'FILES' => $_FILES
    )));
}
