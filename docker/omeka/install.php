<?php

/*
 * Installs Omeka S without the web form, with the same tasks and variables
 * the InstallController registers (Omeka S 4.2.1,
 * application/src/Controller/InstallController.php). Only for the test
 * environment: the first user and its password come from the environment.
 *
 * Exit codes: 0 installed, 2 already installed, 1 failed.
 */

require '/var/www/html/bootstrap.php';

$application = Omeka\Mvc\Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();

if ($services->get('Omeka\Status')->isInstalled()) {
    fwrite(STDOUT, "Omeka S is already installed.\n");
    exit(2);
}

$env = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing environment variable {$name}.\n");
        exit(1);
    }

    return $value;
};

$installer = $services->get('Omeka\Installer');
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

if (!$installer->install()) {
    foreach ($installer->getErrors() as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Omeka S installed.\n");
exit(0);
