<?php
declare(strict_types=1);
session_start();

// --- Pre-flight checks ---

/**
 * Check PHP configuration at startup
 */
$required_min_mb = 100;
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');

$upload_max_bytes = return_bytes($upload_max);
$post_max_bytes = return_bytes($post_max);
$required_bytes = $required_min_mb * 1024 * 1024;

if ($upload_max_bytes < $required_bytes || $post_max_bytes < $required_bytes) {
  die(json_encode(["error" => "Configuration PHP insuffisante. " .
    "upload_max_filesize = {$upload_max}, post_max_size = {$post_max}. " .
    "Minimum requis : {$required_min_mb}M."]));
}

if (!class_exists('ZipArchive')) {
  die(json_encode(["error" => "L'extension PHP Zip (ZipArchive) est requise mais n'est pas activée."]));
}

/**
 * Convert PHP shorthand size string (e.g. '100M', '1G') to bytes
 */
function return_bytes($val)
{
  $val = trim($val);
  $last = strtolower(substr($val, -1));
  $val = (int) $val;
  switch ($last) {
    case 'g':
      $val *= 1024;
    case 'm':
      $val *= 1024;
    case 'k':
      $val *= 1024;
  }
  return $val;
}

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

// --- Nettoyage périodique des fichiers de rate-limit expirés (1% de chance) ---
if (mt_rand(1, 100) === 1) {
  cleanup_expired_rate_files($rate_limit_seconds);
}

/**
 * Supprime les fichiers de rate-limit dont la date d'expiration est dépassée.
 * Appelé aléatoirement à chaque upload pour ne pas pénaliser les performances.
 */
function cleanup_expired_rate_files(int $ttl): void
{
  $pattern = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload_rate_*';
  $files = glob($pattern);
  if ($files === false) return;
  $now = time();
  foreach ($files as $f) {
    if (!is_file($f)) continue;
    $mtime = (int) @filemtime($f);
    // On supprime si expiré depuis au moins 5 minutes (marge de sécurité)
    if ($mtime > 0 && ($now - $mtime) > ($ttl + 300)) {
      @unlink($f);
    }
  }
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

// --- Validate uploaded filename ---
if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $upload_name)) {
  jsonError("Le nom du fichier contient des caractères non autorisés.");
}
if (strlen($upload_name) > 255) {
  jsonError("Le nom du fichier est trop long.");
}
if ($upload_name === '' || $upload_name === '.' || $upload_name === '..') {
  jsonError("Nom de fichier invalide.");
}

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

// --- Ensure upload_folder exists with .htaccess protection ---
$base_upload_folder = __DIR__ . DIRECTORY_SEPARATOR . "upload_folder";
if (!is_dir($base_upload_folder)) {
  if (!mkdir($base_upload_folder, 0755, true)) {
    jsonError("Erreur interne : impossible de créer le dossier d'upload.");
  }
}
$htaccess = $base_upload_folder . DIRECTORY_SEPARATOR . ".htaccess";
if (!file_exists($htaccess)) {
  @file_put_contents($htaccess, "Options -Indexes\nOrder Deny,Allow\nDeny from all\n");
}

// --- Create organized upload directory ---
$upload_folder = $base_upload_folder;
foreach ([date('Ymd'), str_replace(' ', '-', $classe), str_replace(' ', '-', $poste)] as $subfolder) {
  $upload_folder .= DIRECTORY_SEPARATOR . $subfolder;
}

if (!is_dir($upload_folder)) {
  if (!mkdir($upload_folder, 0755, true)) {
    jsonError("Erreur interne : impossible de créer le dossier de destination '$upload_folder'.");
  }
}

// Validate ZIP file (ZipArchive checked in pre-flight)
$zip = new ZipArchive();
if ($zip->open($upload_tmp) !== TRUE) {
  jsonError("Le fichier n'est pas une archive ZIP valide.");
}
$zip->close();

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

  // --- Sauvegarde des hashs SHA-256 (anti-plagiat) si envoyés ---
  if (!empty($_POST['hashes'])) {
    $hashes = json_decode($_POST['hashes'], true);
    if (is_array($hashes) && !empty($hashes)) {
      $hashes_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'hashes.log';
      if (!is_dir(dirname($hashes_file))) {
        @mkdir(dirname($hashes_file), 0755, true);
      }
      $hash_line = date('Y-m-d H:i:s') . ' | ' . $_SERVER['REMOTE_ADDR'] . ' | ' . $classe . ' | ' . $poste . ' | ' . basename($upload_dir) . ' | ' . json_encode($hashes, JSON_UNESCAPED_UNICODE) . PHP_EOL;
      @file_put_contents($hashes_file, $hash_line, FILE_APPEND | LOCK_EX);
    }
  }

  jsonSuccess("Travail envoyé avec succès !");
} else {
  jsonError("Erreur lors de l'enregistrement du fichier. Veuillez réessayer.");
}
