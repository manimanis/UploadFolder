<?php
/**
 * generate_admin_hash.php
 *
 * Script utilitaire pour générer (ou régénérer) le hash bcrypt du mot de
 * passe administrateur. Le hash doit être copié dans data/admin.hash.
 *
 * Usage :
 *   1. Accéder à l'URL : http://localhost/UploadFolder/generate_admin_hash.php
 *   2. Saisir le nouveau mot de passe
 *   3. Cliquer sur "Générer le hash"
 *   4. Copier la valeur affichée dans data/admin.hash
 *
 *   OU en ligne de commande :
 *     php generate_admin_hash.php "MonNouveauMotDePasse"
 *
 * ⚠️ Supprimez ce fichier après utilisation pour des raisons de sécurité.
 */

declare(strict_types=1);
session_start();

$generated_hash = null;
$error = null;

// --- Traitement du formulaire POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
  $new_password = (string) $_POST['password'];
  if (strlen($new_password) < 4) {
    $error = "Le mot de passe doit contenir au moins 4 caractères.";
  } else {
    $generated_hash = password_hash($new_password, PASSWORD_BCRYPT);
  }
}

// --- Traitement de la ligne de commande ---
if (PHP_SAPI === 'cli' && isset($argv[1])) {
  $pwd = $argv[1];
  if (strlen($pwd) < 4) {
    fwrite(STDERR, "Erreur : le mot de passe doit contenir au moins 4 caractères.\n");
    exit(1);
  }
  $hash = password_hash($pwd, PASSWORD_BCRYPT);
  echo "Hash généré pour le mot de passe :\n";
  echo $hash . "\n\n";
  echo "Pour l'utiliser :\n";
  echo "  1. Ouvrez data/admin.hash\n";
  echo "  2. Remplacez son contenu par la ligne ci-dessus\n";
  echo "  3. Supprimez ce script (generate_admin_hash.php) pour la sécurité\n";
  exit(0);
}

// --- Si l'utilisateur clique "Écrire dans data/admin.hash" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['write_directly']) && !empty($_POST['hash_to_write'])) {
  $hash_to_write = (string) $_POST['hash_to_write'];
  $hash_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'admin.hash';
  if (!is_dir(dirname($hash_file))) {
    @mkdir(dirname($hash_file), 0755, true);
  }
  if (@file_put_contents($hash_file, $hash_to_write) !== false) {
    @chmod($hash_file, 0640);
    $success_msg = "✅ Hash écrit dans data/admin.hash avec succès !";
  } else {
    $error = "❌ Impossible d'écrire dans data/admin.hash. Vérifiez les permissions du dossier data/.";
  }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Générer un hash admin - UploadFolder</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <style>
    body {
      background: #f0f7ff;
      padding: 40px 20px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .card {
      max-width: 600px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
      padding: 32px;
    }
    h1 {
      color: #0870b9;
      font-size: 1.4rem;
      margin-bottom: 20px;
    }
    .hash-output {
      background: #1e1e1e;
      color: #d4d4d4;
      padding: 14px;
      border-radius: 8px;
      font-family: 'Consolas', 'Monaco', monospace;
      font-size: 0.85rem;
      word-break: break-all;
      margin: 12px 0;
    }
    .alert {
      padding: 10px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-size: 0.9rem;
    }
    .alert-danger {
      background: #f8d7da;
      color: #721c24;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
    }
    .alert-warning {
      background: #fff3cd;
      color: #856404;
    }
    .form-group {
      margin-bottom: 16px;
    }
    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
    }
    input[type="password"] {
      width: 100%;
      padding: 10px 14px;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      font-size: 0.95rem;
    }
    button {
      background: #0870b9;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 500;
    }
    button:hover {
      background: #065a94;
    }
    .cli-info {
      background: #e8f4fd;
      padding: 12px 16px;
      border-radius: 8px;
      font-family: 'Consolas', 'Monaco', monospace;
      font-size: 0.85rem;
      margin: 10px 0;
    }
  </style>
</head>

<body>
  <div class="card">
    <h1>🔐 Générer un hash de mot de passe admin</h1>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($generated_hash !== null): ?>
      <div class="alert alert-success">✅ Hash généré avec succès !</div>
      <p><strong>Copiez cette ligne dans le fichier <code>data/admin.hash</code> :</strong></p>
      <div class="hash-output"><?= htmlspecialchars($generated_hash) ?></div>

      <form method="post" style="margin-top: 20px;">
        <input type="hidden" name="hash_to_write" value="<?= htmlspecialchars($generated_hash) ?>">
        <input type="hidden" name="write_directly" value="1">
        <button type="submit">💾 Écrire automatiquement dans data/admin.hash</button>
      </form>
    <?php endif; ?>

    <hr style="margin: 24px 0;">

    <form method="post">
      <div class="form-group">
        <label for="password">Nouveau mot de passe admin :</label>
        <input type="password" name="password" id="password" required minlength="4" autofocus>
      </div>
      <button type="submit">🔑 Générer le hash</button>
    </form>

    <hr style="margin: 24px 0;">

    <h2 style="font-size: 1rem; color: #495057;">💻 En ligne de commande</h2>
    <p>Vous pouvez aussi générer le hash directement :</p>
    <div class="cli-info">php generate_admin_hash.php "MonNouveauMotDePasse"</div>

    <div class="alert alert-warning" style="margin-top: 20px;">
      ⚠️ <strong>Important :</strong> Supprimez ce fichier <code>generate_admin_hash.php</code>
      après utilisation pour des raisons de sécurité.
    </div>
  </div>
</body>

</html>
