<?php

if (!defined('APP_FOLDER')) {
    define('APP_FOLDER', sys_get_temp_dir() . '/skankydev_test');
}

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('TIME_HOUR')) {
    define('TIME_HOUR', 3600);
}

// Minimal config required by the framework components under test.
// No database, no filesystem — pure unit test context.
use SkankyDev\Config\Config;

// $conf is null by default — initialise before any set() call
Config::$conf = [];

Config::set('Module', ['App']);
Config::set('default.namespace', 'App');
Config::set('default.action', 'index');
Config::set('view.folder', __DIR__ . '/App/View');
Config::set('view.layout', 'layout.default');
Config::set('view.error', PUBLISHABLE_FOLDER . DS . 'view' . DS . 'error');
Config::set('view.error_layout', PUBLISHABLE_FOLDER . DS . 'view' . DS . 'layout' . DS . 'error.php');
Config::set('template.folder', PUBLISHABLE_FOLDER . DS . 'template');
Config::set('view.fields', __DIR__ . '/App/View/fields');

Config::set('db.MongoDB', [
    'host'     => '127.0.0.1',
    'port'     => 27017,
    'database' => 'skankydev_test',
    'username' => '',
    'password' => '',
]);

Config::set('paginator', ['limit' => 10, 'page' => 1, 'count' => 1, 'range' => 5]);

Config::set('class.parts', []);

Config::set('upload', ['folder' => PUBLIC_FOLDER . DS . 'upload', 'url' => '/upload']);

Config::set('class.fields', [
    'text'     => \SkankyDev\Form\Fields\TextField::class,
    'textarea' => \SkankyDev\Form\Fields\TextareaField::class,
    'number'   => \SkankyDev\Form\Fields\NumberField::class,
    'select'   => \SkankyDev\Form\Fields\SelectField::class,
    'checkbox' => \SkankyDev\Form\Fields\CheckboxField::class,
    'email'    => \SkankyDev\Form\Fields\EmailField::class,
    'hidden'   => \SkankyDev\Form\Fields\HiddenField::class,
    'password' => \SkankyDev\Form\Fields\PasswordField::class,
    'file'     => \SkankyDev\Form\Fields\FileField::class,
]);

Config::set('class.rules', [
    'required'   => \SkankyDev\Validation\Rules\Required::class,
    'email'      => \SkankyDev\Validation\Rules\Email::class,
    'max_length' => \SkankyDev\Validation\Rules\MaxLength::class,
    'min_length' => \SkankyDev\Validation\Rules\MinLength::class,
    'numeric'    => \SkankyDev\Validation\Rules\Numeric::class,
    'max'        => \SkankyDev\Validation\Rules\Max::class,
    'min'        => \SkankyDev\Validation\Rules\Min::class,
    'regex'      => \SkankyDev\Validation\Rules\Regex::class,
    'confirmed'  => \SkankyDev\Validation\Rules\Confirmed::class,
    'same'       => \SkankyDev\Validation\Rules\Same::class,
    'hex_color'  => \SkankyDev\Validation\Rules\HexColor::class,
    'unique'     => \SkankyDev\Validation\Rules\Unique::class,
    'file'       => \SkankyDev\Validation\Rules\File::class,
    'image'      => \SkankyDev\Validation\Rules\Image::class,
    'mimes'      => \SkankyDev\Validation\Rules\Mimes::class,
    'max_size'   => \SkankyDev\Validation\Rules\MaxSize::class,
]);
