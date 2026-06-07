<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Configuration ---
define('ADMIN_PASSWORD', 'admin123');
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'upload_folder');
define('LOG_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'upload.log');
define('EXAMS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'exams.json');

// --- Helpers ---
function json_response(array $data): void
{
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function require_auth(): void
{
  if (empty($_SESSION['admin_logged_in'])) {
    json_response(['error' => 'Non authentifié.']);
  }
}

function formatSize(int $bytes): string
{
  if ($bytes < 1024)
    return $bytes . ' o';
  if ($bytes < 1048576)
    return round($bytes / 1024, 1) . ' Ko';
  if ($bytes < 1073741824)
    return round($bytes / 1048576, 1) . ' Mo';
  return round($bytes / 1073741824, 2) . ' Go';
}

function getDirSize(string $dir): int
{
  $size = 0;
  if (!is_dir($dir))
    return 0;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if ($file->isFile())
      $size += $file->getSize();
  }
  return $size;
}

function countFiles(string $dir): int
{
  $count = 0;
  if (!is_dir($dir))
    return 0;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if ($file->isFile())
      $count++;
  }
  return $count;
}

function deleteRecursive(string $dir): bool
{
  if (!is_dir($dir))
    return false;
  $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
  $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($files as $file) {
    if ($file->isDir()) {
      rmdir($file->getRealPath());
    } else {
      unlink($file->getRealPath());
    }
  }
  return rmdir($dir);
}

function is_safe_path(string $path, string $base): bool
{
  $real = realpath($path);
  $realBase = realpath($base);
  return $real !== false && $realBase !== false && strpos($real, $realBase) === 0;
}

function collectData(): array
{
  $stats = ['total_files' => 0, 'total_size' => 0, 'classes' => []];
  $dates = [];

  if (is_dir(UPLOAD_DIR)) {
    $dateDirs = array_filter(glob(UPLOAD_DIR . '/*'), 'is_dir');
    foreach ($dateDirs as $dateDir) {
      $dateName = (string) basename($dateDir);
      $dateEntry = ['classes' => []];

      $classDirs = array_filter(glob($dateDir . '/*'), 'is_dir');
      foreach ($classDirs as $classDir) {
        $className = (string) basename($classDir);
        $classSize = getDirSize($classDir);
        $classFiles = countFiles($classDir);

        if (!isset($stats['classes'][$className])) {
          $stats['classes'][$className] = ['size' => 0, 'files' => 0];
        }
        $stats['classes'][$className]['size'] += $classSize;
        $stats['classes'][$className]['files'] += $classFiles;

        $posteEntry = [];
        $posteDirs = array_filter(glob($classDir . '/*'), 'is_dir');
        foreach ($posteDirs as $posteDir) {
          $posteName = (string) basename($posteDir);
          $posteSize = getDirSize($posteDir);
          $posteFiles = countFiles($posteDir);

          if ($posteFiles === 0)
            continue;

          $fileList = [];
          $zipFiles = glob($posteDir . '/*.zip');
          foreach ($zipFiles as $zipFile) {
            $fileList[] = [
              'name' => (string) basename($zipFile),
              'size' => filesize($zipFile),
              'sizeFormatted' => formatSize(filesize($zipFile)),
              'mtime' => date('Y-m-d H:i:s', filemtime($zipFile)),
              'relativePath' => $dateName . '/' . $className . '/' . $posteName . '/' . basename($zipFile)
            ];
          }

          $posteEntry[$posteName] = [
            'size' => $posteSize,
            'sizeFormatted' => formatSize($posteSize),
            'files' => $posteFiles,
            'fileList' => $fileList,
            'relativePath' => $dateName . '/' . $className . '/' . $posteName
          ];

          $stats['total_files'] += $posteFiles;
          $stats['total_size'] += $posteSize;
        }

        if (!empty($posteEntry)) {
          $dateEntry['classes'][$className] = $posteEntry;
        }
      }

      $dateTotalFiles = 0;
      $dateTotalSize = 0;
      foreach ($dateEntry['classes'] as $cls) {
        foreach ($cls as $poste) {
          $dateTotalFiles += $poste['files'];
          $dateTotalSize += $poste['size'];
        }
      }
      $dateEntry['totalFiles'] = $dateTotalFiles;
      $dateEntry['totalSize'] = $dateTotalSize;
      $dateEntry['totalSizeFormatted'] = formatSize($dateTotalSize);

      $dates[$dateName] = $dateEntry;
    }
  }
  ksort($dates);

  foreach ($dates as $dateName => $dateEntry) {
    if (empty($dateEntry['classes'])) {
      unset($dates[$dateName]);
    }
  }

  $stats['totalSizeFormatted'] = formatSize($stats['total_size']);

  return ['stats' => $stats, 'dates' => $dates];
}

// --- Routing ---
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

  case 'login':
    $password = $_POST['password'] ?? '';
    if ($password === ADMIN_PASSWORD) {
      $_SESSION['admin_logged_in'] = true;
      json_response(['success' => true]);
    } else {
      json_response(['error' => 'Mot de passe incorrect.']);
    }
    break;

  case 'logout':
    unset($_SESSION['admin_logged_in']);
    json_response(['success' => true]);
    break;

  case 'auth_status':
    json_response(['authenticated' => !empty($_SESSION['admin_logged_in'])]);
    break;

  case 'data':
    require_auth();
    json_response(collectData());
    break;

  case 'log':
    require_auth();
    $lines = [];
    if (file_exists(LOG_FILE)) {
      $raw = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines = array_reverse($raw);
    }
    json_response(['log' => $lines]);
    break;

  case 'download':
    require_auth();
    $path = $_GET['path'] ?? '';
    if ($path === '') {
      http_response_code(400);
      echo 'Chemin manquant.';
      exit;
    }
    $target = UPLOAD_DIR . DIRECTORY_SEPARATOR . $path;
    if (!is_safe_path($target, UPLOAD_DIR) || !is_file($target)) {
      http_response_code(404);
      echo 'Fichier introuvable.';
      exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($target) . '"');
    header('Content-Length: ' . filesize($target));
    readfile($target);
    exit;

  case 'download_multiple': {
    require_auth();
    $paths = json_decode($_POST['paths'] ?? '[]', true);
    if (!is_array($paths) || empty($paths)) {
      http_response_code(400);
      echo 'Aucun fichier sélectionné.';
      exit;
    }
    $validFiles = [];
    foreach ($paths as $p) {
      $target = UPLOAD_DIR . DIRECTORY_SEPARATOR . $p;
      if (is_safe_path($target, UPLOAD_DIR) && is_file($target)) {
        $validFiles[] = ['path' => $target, 'archiveName' => $p];
      }
    }
    if (empty($validFiles)) {
      http_response_code(404);
      echo 'Aucun fichier valide.';
      exit;
    }
    $tmpZip = tempnam(sys_get_temp_dir(), 'multi_dl_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
      foreach ($validFiles as $f) {
        $zip->addFile($f['path'], $f['archiveName']);
      }
      $zip->close();
    }
    if (file_exists($tmpZip) && filesize($tmpZip) > 0) {
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="selection.zip"');
      header('Content-Length: ' . filesize($tmpZip));
      readfile($tmpZip);
      unlink($tmpZip);
      exit;
    }
    http_response_code(500);
    echo 'Erreur lors de la création de l\'archive ZIP.';
    exit;
  }

  case 'download_class':
    require_auth();
    $className = $_GET['class'] ?? '';
    if ($className === '') {
      http_response_code(400);
      echo 'Classe manquante.';
      exit;
    }
    $allFiles = [];
    if (is_dir(UPLOAD_DIR)) {
      $dateDirs = array_filter(glob(UPLOAD_DIR . '/*'), 'is_dir');
      foreach ($dateDirs as $dateDir) {
        $dateName = (string) basename($dateDir);
        $classDir = $dateDir . DIRECTORY_SEPARATOR . $className;
        if (is_dir($classDir)) {
          $posteDirs = array_filter(glob($classDir . '/*'), 'is_dir');
          foreach ($posteDirs as $posteDir) {
            $posteName = (string) basename($posteDir);
            $zipFiles = glob($posteDir . '/*.zip');
            foreach ($zipFiles as $zipFile) {
              $allFiles[] = [
                'path' => $zipFile,
                'archivePath' => $dateName . '/' . $className . '/' . $posteName . '/' . basename($zipFile)
              ];
            }
          }
        }
      }
    }
    if (empty($allFiles)) {
      http_response_code(404);
      echo 'Aucun fichier trouvé pour cette classe.';
      exit;
    }
    $tmpZip = tempnam(sys_get_temp_dir(), 'class_dl_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
      foreach ($allFiles as $f) {
        $zip->addFile($f['path'], $f['archivePath']);
      }
      $zip->close();
    }
    if (file_exists($tmpZip) && filesize($tmpZip) > 0) {
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $className . '.zip"');
      header('Content-Length: ' . filesize($tmpZip));
      readfile($tmpZip);
      unlink($tmpZip);
      exit;
    }
    http_response_code(500);
    echo 'Erreur lors de la création de l\'archive ZIP.';
    exit;

  case 'export_csv':
    require_auth();
    $data = collectData();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="upload_stats.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Classe', 'Poste', 'Fichier', 'Taille', 'Date modification']);
    foreach ($data['dates'] as $dateName => $dateEntry) {
      foreach ($dateEntry['classes'] as $className => $postes) {
        foreach ($postes as $posteName => $posteData) {
          foreach ($posteData['fileList'] as $file) {
            fputcsv($output, [
              $dateName,
              $className,
              $posteName,
              $file['name'],
              $file['sizeFormatted'],
              $file['mtime']
            ]);
          }
        }
      }
    }
    fclose($output);
    exit;

  case 'exams_list':
    require_auth();
    $exams = [];
    if (file_exists(EXAMS_FILE)) {
      $raw = file_get_contents(EXAMS_FILE);
      $exams = json_decode($raw, true);
    }
    // Sort by date descending, then by time_start descending
    usort($exams['exams'], function ($a, $b) {
      $da = ($a['date'] ?? '') . ' ' . ($a['time_start'] ?? '');
      $db = ($b['date'] ?? '') . ' ' . ($b['time_start'] ?? '');
      return strcmp($db, $da);
    });
    json_response([
      'exams' => $exams['exams'] ?? [],
      'noms' => $exams['noms'] ?? [],
      'matieres' => $exams['matieres'] ?? [],
      'enseignants' => $exams['enseignants'] ?? [],
      'classes' => $exams['classes'] ?? []
    ]);
    break;

  case 'exams_save':
    require_auth();
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $date = $_POST['date'] ?? '';
    $time_start = $_POST['time_start'] ?? '';
    $time_end = $_POST['time_end'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $teacher = $_POST['teacher'] ?? '';
    $classes_raw = $_POST['classes'] ?? '';
    $classes = $classes_raw !== '' ? explode(',', $classes_raw) : [];

    if ($name === '' || $date === '' || $time_start === '' || $time_end === '' || $subject === '' || $teacher === '') {
      json_response(['error' => 'Tous les champs sont obligatoires.']);
    }

    // Load existing exams file
    $exams = [];
    $meta = [];
    if (file_exists(EXAMS_FILE)) {
      $raw = file_get_contents(EXAMS_FILE);
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $exams = $decoded['exams'] ?? [];
        $meta = $decoded;
        unset($meta['exams']);
      }
    }

    if ($id !== '') {
      // Update existing exam
      $found = false;
      foreach ($exams as &$exam) {
        if (($exam['id'] ?? '') === $id) {
          $exam['name'] = $name;
          $exam['date'] = $date;
          $exam['time_start'] = $time_start;
          $exam['time_end'] = $time_end;
          $exam['subject'] = $subject;
          $exam['teacher'] = $teacher;
          $exam['classes'] = $classes;
          $found = true;
          break;
        }
      }
      unset($exam);
      if (!$found) {
        json_response(['error' => 'Examen introuvable.']);
      }
    } else {
      // Create new exam
      $exams[] = [
        'id' => bin2hex(random_bytes(16)),
        'name' => $name,
        'date' => $date,
        'time_start' => $time_start,
        'time_end' => $time_end,
        'subject' => $subject,
        'teacher' => $teacher,
        'classes' => $classes
      ];
    }

    // Auto-collect metadata from all exams
    $noms = [];
    $matieres = [];
    $enseignants = [];
    $allClasses = [];
    foreach ($exams as $exam) {
      if (!empty($exam['name'])) $noms[] = $exam['name'];
      if (!empty($exam['subject'])) $matieres[] = $exam['subject'];
      if (!empty($exam['teacher'])) $enseignants[] = $exam['teacher'];
      if (!empty($exam['classes']) && is_array($exam['classes'])) {
        foreach ($exam['classes'] as $c) {
          $c = trim($c);
          if ($c !== '') $allClasses[] = $c;
        }
      }
    }
    $noms = array_values(array_unique($noms));
    $matieres = array_values(array_unique($matieres));
    $enseignants = array_values(array_unique($enseignants));
    $allClasses = array_values(array_unique($allClasses));

    $data = [
      'noms' => $noms,
      'matieres' => $matieres,
      'enseignants' => $enseignants,
      'classes' => $allClasses,
      'exams' => $exams
    ];

    file_put_contents(EXAMS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    json_response(['success' => true]);
    break;

  case 'exams_delete':
    require_auth();
    $id = $_POST['id'] ?? '';
    if ($id === '') {
      json_response(['error' => 'ID manquant.']);
    }

    $exams = [];
    $meta = [];
    if (file_exists(EXAMS_FILE)) {
      $raw = file_get_contents(EXAMS_FILE);
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $exams = $decoded['exams'] ?? [];
        $meta = $decoded;
        unset($meta['exams']);
      }
    }

    $exams = array_values(array_filter($exams, function ($e) use ($id) {
      return ($e['id'] ?? '') !== $id;
    }));

    // Re-collect metadata from remaining exams
    $noms = [];
    $matieres = [];
    $enseignants = [];
    $allClasses = [];
    foreach ($exams as $exam) {
      if (!empty($exam['name'])) $noms[] = $exam['name'];
      if (!empty($exam['subject'])) $matieres[] = $exam['subject'];
      if (!empty($exam['teacher'])) $enseignants[] = $exam['teacher'];
      if (!empty($exam['classes']) && is_array($exam['classes'])) {
        foreach ($exam['classes'] as $c) {
          $c = trim($c);
          if ($c !== '') $allClasses[] = $c;
        }
      }
    }
    $noms = array_values(array_unique($noms));
    $matieres = array_values(array_unique($matieres));
    $enseignants = array_values(array_unique($enseignants));
    $allClasses = array_values(array_unique($allClasses));

    $data = [
      'noms' => $noms,
      'matieres' => $matieres,
      'enseignants' => $enseignants,
      'classes' => $allClasses,
      'exams' => $exams
    ];

    file_put_contents(EXAMS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    json_response(['success' => true]);
    break;

  case 'open_folder':
    require_auth();
    $date = $_GET['date'] ?? '';
    $class = $_GET['class'] ?? '';
    $poste = $_GET['poste'] ?? '';
    $target = UPLOAD_DIR;
    if ($date !== '') {
      $target .= DIRECTORY_SEPARATOR . $date;
    }
    if ($class !== '') {
      $target .= DIRECTORY_SEPARATOR . $class;
    }
    if ($poste !== '') {
      $target .= DIRECTORY_SEPARATOR . $poste;
    }
    if (($date === '' && $class !== '') || ($date === '' && $class === '' && $poste !== '')) {
      json_response(['error' => 'Paramètres manquants.']);
    }
    if (!is_dir($target) || !is_safe_path($target, UPLOAD_DIR)) {
      json_response(['error' => 'Dossier introuvable.']);
    }
    if (PHP_OS_FAMILY === 'Windows') {
      exec('explorer "' . $target . '"');
    } else {
      exec('xdg-open "' . $target . '"');
    }
    json_response(['success' => true]);
    break;

  default:
    json_response(['error' => 'Action inconnue.']);
}