<?php
// Build a distributable LMS update package (.tar.gz) from the current code.
// Hand the resulting file to any install; an admin uploads it at Admin → Updates
// to bring that install's LMS code up to date without touching data or content.
//
//   php bin/build-update.php [output_dir] [--key=/path/update-signing.key]
//
// With --key (or env SAPIQO_UPDATE_KEY) the package is signed; installs refuse
// unsigned packages unless their config.local.php sets 'allow_unsigned_updates'.
// New key pair:  php bin/build-update.php --keygen=/path/update-signing.key

declare(strict_types=1);

require_once __DIR__ . '/../app/updater.php';

$opts = []; $pos = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(key|keygen)=(.+)$/', $arg, $m)) $opts[$m[1]] = $m[2]; else $pos[] = $arg;
}
if (isset($opts['keygen'])) {
    if (file_exists($opts['keygen'])) { fwrite(STDERR, "Refusing to overwrite {$opts['keygen']}\n"); exit(1); }
    $kp = sodium_crypto_sign_keypair();
    umask(077);
    file_put_contents($opts['keygen'], base64_encode(sodium_crypto_sign_secretkey($kp)) . "\n");
    printf("Secret key written to %s (keep it private, off the server).\nPublic key (add to 'update_trusted_keys'): %s\n",
        $opts['keygen'], base64_encode(sodium_crypto_sign_publickey($kp)));
    exit(0);
}
$out = $pos[0] ?? getcwd();
$keyFile = $opts['key'] ?? (getenv('SAPIQO_UPDATE_KEY') ?: null);
try {
    $sk = $keyFile !== null ? update_load_secret_key($keyFile) : null;
    $path = build_update_package($out, [], $sk);
    printf("Built %s update package: %s\n  version %s, schema %d\n", $sk ? 'SIGNED' : 'UNSIGNED',
        $path, update_current_version(), defined('DB_SCHEMA_VERSION') ? DB_SCHEMA_VERSION : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . "\n");
    exit(1);
}
