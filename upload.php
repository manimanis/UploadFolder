<?php
declare(strict_types=1);
session_start();

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
  $input = preg_replace('/\.\./', '', $input);
  $input = preg_replace('/[^\w\s.\-]/u', '', $input);
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

// --- CSRF Token validation ---
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  jsonError("Token de sécurité invalide. Veuillez recharger la page et réessayer.");
}

// --- Rate limiting (IP-based: 1 upload per 30 seconds) ---
$rate_limit_seconds = 30;
$rate_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload_rate_' . md5($_SERVER['REMOTE_ADDR']);
$now = time();
if (file_exists($rate_file)) {
  $last_upload = (int) file_get_contents($rate_file);
  if ($now - $last_upload < $rate_limit_seconds) {
    $remaining = $rate_limit_seconds - ($now - $last_upload);
    jsonError("Veuillez patienter {$remaining} secondes avant le prochain envoi.");
  }
}
file_put_contents($rate_file, (string) $now);

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

// --- File size validation (PHP side max 100MB) ---
$max_size = 100 * 1024 * 1024; // 100 MB
$file_size = filesize($upload_tmp);
if ($file_size === false || $file_size > $max_size) {
  jsonError("Le fichier est trop volumineux. Taille maximale acceptée : 100 Mo.");
}

// --- MIME type validation for the ZIP blob sent by the client ---
// The client sends a ZIP via FormData, so MIME should be application/zip
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $upload_tmp);
finfo_close($finfo);
$allowed_mimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed'];
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

// Validate ZIP file (with fallback if ZipArchive extension is not available)
if (class_exists('ZipArchive')) {
  $zip = new ZipArchive();
  if ($zip->open($upload_tmp) !== TRUE) {
    jsonError("Le fichier n'est pas une archive ZIP valide.");
  }
  $zip->close();
} elseif (function_exists('zip_open')) {
  $zip = @zip_open($upload_tmp);
  if (is_resource($zip)) {
    zip_close($zip);
  } else {
    jsonError("Le fichier n'est pas une archive ZIP valide.");
  }
} else {
  // Fallback: check ZIP magic bytes (PK\x03\x04)
  $handle = @fopen($upload_tmp, 'rb');
  if ($handle) {
    $magic = fread($handle, 4);
    fclose($handle);
    if ($magic !== "PK\x03\x04" && $magic !== "PK\x05\x06" && $magic !== "PK\x07\x08") {
      jsonError("Le fichier n'est pas une archive ZIP valide.");
    }
  } else {
    jsonError("Impossible de vérifier le fichier uploadé.");
  }
}

// --- Generate unique filename ---
$file = date('Ymd_His');
$file .= '_' . str_replace('.', '-', $_SERVER['REMOTE_ADDR']);

$count = 0;
do {
  $upload_dir = $upload_folder . DIRECTORY_SEPARATOR . $file . ($count === 0 ? "" : (string) $count) . ".zip";
  $count++;
} while (file_exists($upload_dir));

// --- Move uploaded file ---
if (move_uploaded_file($upload_tmp, $upload_dir)) {
  // --- Journalisation des uploads ---
  $log_line = date('Y-m-d H:i:s') . ' | ' . $_SERVER['REMOTE_ADDR'] . ' | ' . $classe . ' | ' . $poste . ' | ' . $file_size . ' bytes | ' . basename($upload_dir) . PHP_EOL;
  @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'upload.log', $log_line, FILE_APPEND | LOCK_EX);
  jsonSuccess("Travail envoyé avec succès !");
} else {
  jsonError("Erreur lors de l'enregistrement du fichier. Veuillez réessayer.");
}
