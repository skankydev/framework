<?php

// Volontairement sans variable $conf : le fichier retourne directement son tableau.
return [
	'Module'      => ['Alpha', 'Beta'],
	'only_master' => true,
	'shared'      => ['c' => 'master'],
];
