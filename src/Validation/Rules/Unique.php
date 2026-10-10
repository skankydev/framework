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

namespace SkankyDev\Validation\Rules;

use SkankyDev\Validation\Rules\Rule;

/**
 * Vérifie qu'aucun document de la Collection donnée n'a déjà cette valeur.
 * Usage : `'rules' => ['unique:' . \App\Model\UserCollection::class]` (colonne = nom du champ)
 * ou `'unique:' . \App\Model\UserCollection::class . ',slug'` pour une colonne différente.
 *
 * Édition : passer l'_id du document en cours en 3e paramètre pour l'exclure
 * du check (sinon un update sans changer la valeur se rejette lui-même) :
 * `'unique:' . UserCollection::class . ',email,' . $user->_id`
 * — typiquement construit dans `build()` à partir de `$this->data['_id']`,
 * l'_id n'étant pas soumis dans le POST (il vient de l'URL, pas du form).
 */
class Unique extends Rule {

	public function __construct(
		protected string $collectionClass,
		protected ?string $column = null,
		protected ?string $exceptId = null
	) {}

	public function check(string $field, mixed $value, array $data = []): bool {
		if ($this->isEmpty($value)) {
			return true;
		}

		if (!class_exists($this->collectionClass)) {
			throw new \Exception("Unique : collection introuvable « {$this->collectionClass} »", 500);
		}

		$column     = $this->column ?? $field;
		$collection = $this->collectionClass::getInstance();
		$match      = $collection->findOne([$column => $value]);

		if ($match === null) {
			return true;
		}

		return $this->exceptId !== null && (string) $match->_id === (string) $this->exceptId;
	}

	public function message(string $field): string {
		return __('skankydev.validation.unique', ['field' => $field]);
	}
}
