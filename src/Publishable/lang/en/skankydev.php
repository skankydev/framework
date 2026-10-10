<?php
/**
 * Messages du framework, en anglais (format ICU MessageFormat).
 * Domaine `skankydev` : __('skankydev.validation.required', ['field' => 'email'])
 *
 * Pour les personnaliser : `php craft publish -p=lang`, puis modifie la copie
 * dans le dossier de langues du projet (elle remplace ce fichier en entier).
 */

return [
	'validation' => [
		'required'   => 'The {field} field is required',
		'email'      => 'The {field} field must be a valid email address',
		'max_length' => 'The {field} field must not exceed {max, plural, one {# character} other {# characters}}',
		'min_length' => 'The {field} field must contain at least {min, plural, one {# character} other {# characters}}',
		'numeric'    => 'The {field} field must be a number',
		'max'        => 'The {field} field must be less than or equal to {max}',
		'min'        => 'The {field} field must be greater than or equal to {min}',
		'regex'      => 'The {field} field format is invalid',
		'confirmed'  => 'The {field} field confirmation does not match',
		'unique'     => 'The {field} field is already taken',
		'same'       => 'The {field} field must match {other}',
		'hex_color'  => 'The {field} field must be a valid hexadecimal color',
		'file'       => 'The {field} field must be a valid file',
		'image'      => 'The {field} field must be an image',
		'mimes'      => 'The {field} field must be a file of type: {extensions}',
		'max_size'   => 'The {field} file must not exceed {kilobytes} KB',
	],
	'upload' => [
		'ini_size'   => 'The file exceeds the maximum size allowed by the server',
		'form_size'  => 'The file exceeds the maximum size allowed by the form',
		'partial'    => 'The file was only partially uploaded',
		'no_file'    => 'No file was uploaded',
		'no_tmp_dir' => 'Missing temporary folder on the server',
		'cant_write' => 'Failed to write the file to disk',
		'extension'  => 'Upload blocked by a PHP extension',
		'invalid'    => 'Invalid uploaded file',
		'unknown'    => 'Unknown upload error',
	],
];
