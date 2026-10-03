<?php 
/**
 * Copyright (c) 2025 SCHENCK Simon
 * 
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright     Copyright (c) SCHENCK Simon
 *
 */

return [
	'default' => [
		'namespace' => 'App',
		'action'    => 'index'
	],
	'paginator'=>[
		'limit' => 10,
		'page'  => 1,
		'count' => 1,
		'range' => 5,
	],
	'skankydev'  => [
		'version'=>'0.1.1'
	],
	'debug' => true,
	'auth' => false,
	'middlewares'=>[
		'Session'=>'Session',
		'Csrf'=>'Csrf'
	],
	'class' => [
		'middlewares' => [
			'Session'  => \SkankyDev\Http\Middleware\SessionMiddleware::class,
			'Csrf'     => \SkankyDev\Http\Middleware\CsrfMiddleware::class,
			'PostOnly'  => \SkankyDev\Http\Middleware\PostOnlyMiddleware::class,
			'Auth'      => \SkankyDev\Http\Middleware\AuthMiddleware::class,
			'RateLimit' => \SkankyDev\Http\Middleware\RateLimitMiddleware::class,
		],
		'gates' => [
			'session' => \SkankyDev\Auth\Gates\SessionGate::class,
		],
		'fields' => [
			'text'     => \SkankyDev\Form\Fields\TextField::class,
			'textarea' => \SkankyDev\Form\Fields\TextareaField::class,
			'color'    => \SkankyDev\Form\Fields\ColorField::class,
			'number'   => \SkankyDev\Form\Fields\NumberField::class,
			'select'   => \SkankyDev\Form\Fields\SelectField::class,
			'checkbox' => \SkankyDev\Form\Fields\CheckboxField::class,
			'email'    => \SkankyDev\Form\Fields\EmailField::class,
			'radio'    => \SkankyDev\Form\Fields\RadioField::class,
			'date'     => \SkankyDev\Form\Fields\DateField::class,
			'datetime' => \SkankyDev\Form\Fields\DateTimeField::class,
			'password' => \SkankyDev\Form\Fields\PasswordField::class,
			'file'     => \SkankyDev\Form\Fields\FileField::class,
			'hidden'   => \SkankyDev\Form\Fields\HiddenField::class,
			'default'  => \SkankyDev\Form\Fields\TextField::class,
		],
		'rules' => [
			'required'   => \SkankyDev\Validation\Rules\Required::class,
			'email'      => \SkankyDev\Validation\Rules\Email::class,
			'max_length' => \SkankyDev\Validation\Rules\MaxLength::class,
			'min_length' => \SkankyDev\Validation\Rules\MinLength::class,
			'numeric'    => \SkankyDev\Validation\Rules\Numeric::class,
			'max'        => \SkankyDev\Validation\Rules\Max::class,
			'min'        => \SkankyDev\Validation\Rules\Min::class,
			'regex'      => \SkankyDev\Validation\Rules\Regex::class,
			'confirmed'  => \SkankyDev\Validation\Rules\Confirmed::class,
			'unique'     => \SkankyDev\Validation\Rules\Unique::class,
			'same'       => \SkankyDev\Validation\Rules\Same::class,
			'hex_color'  => \SkankyDev\Validation\Rules\HexColor::class,
		],
		'parts' => [],
	],
	'view' => [
		'folder' => VIEW_FOLDER,
		'layout' => 'layout.default',
		'error' => PUBLISHABLE_FOLDER.DS.'view'.DS.'error',
		'error_layout' => PUBLISHABLE_FOLDER.DS.'view'.DS.'layout'.DS.'error.php',
		'fields' => PUBLISHABLE_FOLDER.DS.'view'.DS.'fields',
	],
	'template' => [
		'folder' => PUBLISHABLE_FOLDER.DS.'template',
	],
	'timeHelper'=> [
		'format'=>'Y-m-d H:i:s',
		'timezone'=>'UTC'
	],
];
