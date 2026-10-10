%?php
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

<?php
$defaultValue = [
	'string'   => 'text',
	'int'      => 'number',
	'float'    => 'number',
	'bool'     => 'checkbox',
	'date'     => 'date',
	'datetime' => 'datetime',
	'array'    => 'textarea',
];
$fkFields = array_filter($this->fields, fn($f) => $f['type'] === 'ObjectId');
?>
namespace <?= $module ?>\Form;

use SkankyDev\Form\FormBuilder;

class <?= $name ?>Form extends FormBuilder {

	public function build() : void {

<?php foreach($this->fields as $field): ?>
<?php if($field['type'] === 'ObjectId'): ?>
		$this->add('<?= $field['name'] ?>','select',[
			'label' => '<?= $this->fkRelated($field['name']) ?>',
			'rules' => [<?= $field['required'] ? "'required'" : '' ?>],
			'empty' => '--- Choisir ---',
			'options' => $this-><?= $this->toCamel($field['name'],'_') ?>Options(),
		]);
<?php else: ?>
		$this->add('<?= $field['name'] ?>','<?= $defaultValue[$field['type']] ?? 'text' ?>',[
			'label' => '<?= $this->toHuman($field['name']) ?>',
			'rules' => [<?= $field['required'] ? "'required'" : '' ?>]
		]);
<?php endif; ?>
<?php endforeach; ?>

		$this->submit('SAVE');
	}
<?php foreach($fkFields as $field): ?>

	/**
	 * id (string) => name, for the related <?= $this->fkRelated($field['name']) ?> select.
	 */
	private function <?= $this->toCamel($field['name'],'_') ?>Options(): array {
		$options = [];
		foreach (\<?= $module ?>\Model\<?= $this->fkRelated($field['name']) ?>Collection::_find() as $item) {
			$options[(string) $item->_id] = $item->name;
		}
		return $options;
	}
<?php endforeach; ?>
}
