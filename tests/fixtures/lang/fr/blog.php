<?php
return [
	'label' => 'Titre',
	'post'  => [
		'title'   => 'Article : {title}',
		'count'   => '{count, plural, =0 {Aucun article} one {# article} other {# articles}}',
		'nb'      => '{count, plural, one {# article} other {# articles}}',
		'by'      => '{gender, select, female {Écrite par {name}} other {Écrit par {name}}}',
		'only_fr' => 'Seulement en français',
		'broken'  => 'Message cassé {oops',
		'quote'   => 'L’article de {name}',
	],
];
