<?php
/**
 * Messages du framework, en français (format ICU MessageFormat).
 * Domaine `skankydev` : __('skankydev.validation.required', ['field' => 'email'])
 *
 * Pour les personnaliser : `php craft publish -p=lang`, puis modifie la copie
 * dans le dossier de langues du projet (elle remplace ce fichier en entier).
 */

return [
	'validation' => [
		'required'   => 'Le champ {field} est requis',
		'email'      => 'Le champ {field} doit être un email valide',
		'max_length' => 'Le champ {field} doit contenir maximum {max, plural, one {# caractère} other {# caractères}}',
		'min_length' => 'Le champ {field} doit contenir au moins {min, plural, one {# caractère} other {# caractères}}',
		'numeric'    => 'Le champ {field} doit être un nombre',
		'max'        => 'Le champ {field} doit être inférieur ou égal à {max}',
		'min'        => 'Le champ {field} doit être supérieur ou égal à {min}',
		'regex'      => 'Le format du champ {field} est invalide',
		'confirmed'  => 'La confirmation du champ {field} ne correspond pas',
		'unique'     => 'Ce champ {field} est déjà utilisé',
		'same'       => 'Le champ {field} doit être identique à {other}',
		'hex_color'  => 'Le champ {field} doit être une couleur hexadécimale valide',
		'file'       => 'Le champ {field} doit être un fichier valide',
		'image'      => 'Le champ {field} doit être une image',
		'mimes'      => 'Le champ {field} doit être un fichier de type : {extensions}',
		'max_size'   => 'Le fichier {field} ne doit pas dépasser {kilobytes} Ko',
	],
	'upload' => [
		'ini_size'   => 'Le fichier dépasse la taille maximale autorisée par le serveur',
		'form_size'  => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
		'partial'    => 'Le fichier n’a été que partiellement envoyé',
		'no_file'    => 'Aucun fichier envoyé',
		'no_tmp_dir' => 'Dossier temporaire manquant sur le serveur',
		'cant_write' => 'Impossible d’écrire le fichier sur le disque',
		'extension'  => 'Envoi bloqué par une extension PHP',
		'invalid'    => 'Fichier envoyé invalide',
		'unknown'    => 'Erreur inconnue lors de l’envoi',
	],
];
