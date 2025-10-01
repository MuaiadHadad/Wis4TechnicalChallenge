<?php

// Valid PHP Version?
$minPHPVersion = '7.4';
if (phpversion() < $minPHPVersion) {
    die("Your PHP version must be {$minPHPVersion} or higher to run CodeIgniter. Current version: " . phpversion());
}

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// Define ROOTPATH
define('ROOTPATH', realpath(FCPATH . '../') . DIRECTORY_SEPARATOR);

// Location of the Paths config file.
require FCPATH . '../app/Config/Paths.php';

$paths = new Config\Paths();

// Location of the framework bootstrap file.
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (!file_exists($bootstrap)) {
    // Try alternative path for vendor installation
    $bootstrap = ROOTPATH . 'vendor/codeigniter4/framework/system/bootstrap.php';
}

$app = require realpath($bootstrap) ?: $bootstrap;

// Load environment settings from .env files into $_SERVER and $_ENV
require_once ROOTPATH . 'vendor/codeigniter4/framework/system/Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

/*
 *---------------------------------------------------------------
 * LAUNCH THE APPLICATION
 *---------------------------------------------------------------
 * Now that everything is setup, it's time to actually fire
 * up the engines and make this app do its thang.
 */

$app->run();
