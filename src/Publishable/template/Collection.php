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

namespace App\Model;

use SkankyDev\Utilities\Traits\Singleton;
use SkankyDev\Model\MasterCollection;
use App\Model\Document\<?= $name ?>;

class <?= $name ?>Collection extends MasterCollection {

	use Singleton;

	protected string $collectionName = '<?= $collection ?>';
	protected string $documentClass = <?= $name ?>::class;
<?php $fkFields = array_filter($this->fields, fn($f) => $f['type'] === 'ObjectId'); ?>
<?php if(!empty($fkFields)): ?>

	public function relations(): array {
		return [
<?php foreach($fkFields as $field): ?>
			'<?= lcfirst($this->fkRelated($field['name'])) ?>' => ['type' => 'belongsTo', 'collection' => <?= $this->fkRelated($field['name']) ?>Collection::class, 'key' => '<?= $field['name'] ?>'],
<?php endforeach; ?>
		];
	}
<?php endif; ?>

	public function getDisplayField(): array {
		return [
<?php foreach($this->fields as $field): ?>
<?php if($field['type'] === 'ObjectId'): ?>
			'<?= $field['name'] ?>' => [
				'label'  => '<?= $this->fkRelated($field['name']) ?>',
				'sort'   => true,
				'render' => fn($<?= $singularCamel ?>) => e($<?= $singularCamel ?>-><?= lcfirst($this->fkRelated($field['name'])) ?>?->name ?? '—'),
			],
<?php else: ?>
			'<?= $field['name'] ?>' => ['label' => '<?= $this->toHuman($field['name']) ?>', 'sort' => true],
<?php endif; ?>
<?php endforeach; ?>
			'created_at' => ['label' => 'Created', 'sort' => true],
			'updated_at' => ['label' => 'Updated', 'sort' => true],
		];
	}

}