<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Throw-away RSA key pair for JWTs in the test environment (keys are git-ignored).
$jwtDir = dirname(__DIR__).'/config/jwt';
if (!is_file($jwtDir.'/test_private.pem')) {
    @mkdir($jwtDir, 0777, true);
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private, 'test');
    file_put_contents($jwtDir.'/test_private.pem', $private);
    file_put_contents($jwtDir.'/test_public.pem', openssl_pkey_get_details($key)['key']);
}
