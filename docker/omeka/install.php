<?php

declare(strict_types=1);

use Omeka\Installation\Installer;
use Omeka\Mvc\Application;
use Omeka\Mvc\Status;

/*
 * Installs Omeka S without the web form, with the same tasks and variables
 * the InstallController registers (Omeka S 4.2.1,
 * application/src/Controller/InstallController.php). Only for the test
 * environment: Omeka S's directory (OMEKA_ROOT), the first user and its
 * password come from the environment.
 *
 * Exit codes: 0 installed, 2 already installed, 1 failed.
 */

$fail = static function (string $message): never {
    fwrite(STDERR, $message."\n");
    exit(1);
};

$env = static function (string $name) use ($fail): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        $fail("Missing environment variable {$name}.");
    }

    return $value;
};

// Where Omeka S lives in the container; from the environment, as the rest.
$omekaPath = $env('OMEKA_ROOT');
require $omekaPath.'/bootstrap.php';

$services = Application::init(require $omekaPath.'/application/config/application.config.php')->getServiceManager();

$status = $services->get('Omeka\Status');
if (! $status instanceof Status) {
    $fail('The service Omeka\Status is not an Omeka\Mvc\Status.');
}
if ($status->isInstalled()) {
    fwrite(STDOUT, "Omeka S is already installed.\n");
    exit(2);
}

$installer = $services->get('Omeka\Installer');
if (! $installer instanceof Installer) {
    $fail('The service Omeka\Installer is not an Omeka\Installation\Installer.');
}
$installer->registerVars('Omeka\Installation\Task\CreateFirstUserTask', [
    'name' => 'Test administrator',
    'email' => $env('OMEKA_ADMIN_EMAIL'),
    'password-confirm' => ['password' => $env('OMEKA_ADMIN_PASSWORD')],
]);
$installer->registerVars('Omeka\Installation\Task\AddDefaultSettingsTask', [
    'administrator_email' => $env('OMEKA_ADMIN_EMAIL'),
    'installation_title' => 'Content Credentials test environment',
    'time_zone' => 'UTC',
    'locale' => 'en_US',
]);

if (! $installer->install()) {
    foreach ($installer->getErrors() as $error) {
        fwrite(STDERR, (is_string($error) ? $error : 'unknown error')."\n");
    }
    exit(1);
}

fwrite(STDOUT, "Omeka S installed.\n");
exit(0);
