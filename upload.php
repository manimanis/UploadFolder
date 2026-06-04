<?php

/**
 * Sanitize a string for use in file paths
 * Removes accents, special chars, prevents path traversal
 */
function sanitizePathComponent($input)
{
  // Transliterate accents to ASCII equivalents
  if (function_exists('iconv')) {
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
    if ($transliterated !== false) {
      $input = $transliterated;
    }
  }
  // Remove any character that is not alphanumeric, underscore, hyphen, dot, or space
  $input = preg_replace('/[^\w\s.\-]/u', '', $input);
  $input = preg_replace('/\s+/', ' ', $input);
  return trim($input);
}

/**
 * Return a JSON error response, without exposing sensitive debug data
 */
function jsonError($message)
{
  die(json_encode(["error" => $message]));
}

/**
 * Return a JSON success response
 */
function jsonSuccess($message)
{
  die(json_encode(["success" => $message]));
}

// --- Route: hello check ---
if (isset($_POST["hello"])) {
  jsonSuccess("Welcome to Upload Server!");
}

// --- Require upload flag ---
if (!isset($_POST['upload'])) {
  jsonError("Erreur : données de formulaire manquantes.");
}

// --- Validate required inputs ---
$poste = empty($_POST['poste']) ? '' : sanitizePathComponent($_POST['poste']);
$classe = empty($_POST['classe']) ? '' : sanitizePathComponent($_POST['classe']);

if ($classe === '') {
  jsonError("Veuillez indiquer votre classe (obligatoire).");
}

// If no name provided, use a sanitized IP address
if ($poste === '') {
  $poste = str_replace('.', '-', $_SERVER['REMOTE_ADDR']);
}

// --- Validate upload file ---
if (!isset($_FILES['files']) || !isset($_FILES['files']['tmp_name']) || empty($_FILES['files']['tmp_name'])) {
  jsonError("Aucun fichier reçu. Veuillez sélectionner un fichier/dossier à soumettre.");
}

// Check if uploaded file is a blob (from JSZip client-side compression) or actual file
$upload_tmp = $_FILES['files']['tmp_name'];
$upload_name = basename($_FILES['files']['name']);

// --- File size validation (PHP side max 50MB) ---
$max_size = 50 * 1024 * 1024; // 50 MB
$file_size = filesize($upload_tmp);
if ($file_size === false || $file_size > $max_size) {
  jsonError("Le fichier est trop volumineux. Taille maximale acceptée : 50 Mo.");
}

// --- MIME type validation for the ZIP blob sent by the client ---
// The client sends a ZIP via FormData, so MIME should be application/zip
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $upload_tmp);
finfo_close($finfo);
$allowed_mimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'];
if (!in_array($mime, $allowed_mimes)) {
  jsonError("Type de fichier non accepté. Seuls les fichiers ZIP sont autorisés.");
}

// --- Create organized upload directory ---
$upload_folder = __DIR__ . DIRECTORY_SEPARATOR . "upload_folder";
foreach ([date('Ymd'), str_replace(' ', '-', $classe), str_replace(' ', '-', $poste)] as $subfolder) {
  $upload_folder .= DIRECTORY_SEPARATOR . $subfolder;
}

if (!is_dir($upload_folder)) {
  if (!mkdir($upload_folder, 0755, true)) {
    jsonError("Erreur interne : impossible de créer le dossier de destination '$upload_folder'.");
  }
}

// --- Generate unique filename ---
$file = date('His');
$file .= '_' . str_replace('.', '-', $_SERVER['REMOTE_ADDR']);

$count = 0;
do {
  $upload_dir = $upload_folder . DIRECTORY_SEPARATOR . $file . ($count === 0 ? "" : (string) $count) . ".zip";
  $count++;
} while (file_exists($upload_dir));

// --- Move uploaded file ---
if (move_uploaded_file($upload_tmp, $upload_dir)) {
  jsonSuccess("Travail envoyé avec succès !");
} else {
  jsonError("Erreur lors de l'enregistrement du fichier. Veuillez réessayer.");
}