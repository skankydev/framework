%?php
$this->setLayout('layout.default');
$this->addCrumb('<?= $name ?>', ['action' => 'index'], '');
$this->addCrumb($<?= $singularCamel ?>-><?= $this->labelField() ?>, ['action' => 'show', 'params' => [$<?= $singularCamel ?>->_id]], '');
?>

<header class="page-header">
	<div class="page-title-wrapper">
		<h2 class="page-title">
			<i class="text-secondary "></i>
			%?= e($<?= $singularCamel ?>-><?= $this->labelField() ?>) ?>
		</h2>
	</div>
	<div class="page-action">
		<a href="%?= $this->url(['action'=>'edit','params'=>[$<?= $singularCamel ?>->_id]]) ?>" class="btn btn-primary">
			<i class="icon-edit"></i>
			Edit
		</a>
		<a href="%?= $this->url(['action'=>'delete','params'=>[$<?= $singularCamel ?>->_id]]) ?>" class="btn btn-error"
			data-method="post" data-confirm="Supprimer ?">
			<i class="icon-delete"></i>
			Delete
		</a>
	</div>
</header>

<section class="page-content grid-layout">
	<div class="grid-half card p-m">
		<dl>
<?php foreach($this->fields as $field): ?>
<?php if($field['type'] === 'ObjectId'): ?>
			<dt><?= $this->fkRelated($field['name']) ?></dt>
			<dd>%?= e($<?= $singularCamel ?>-><?= lcfirst($this->fkRelated($field['name'])) ?>?->name ?? '—') ?></dd>
<?php elseif($field['type'] === 'bool'): ?>
			<dt><?= $this->toHuman($field['name']) ?></dt>
			<dd><span class="text-%?= $<?= $singularCamel ?>-><?= $field['name'] ?> ? 'success' : 'error' ?>">%?= $<?= $singularCamel ?>-><?= $field['name'] ?> ? 'oui' : 'non' ?></span></dd>
<?php elseif($field['type'] === 'date'): ?>
			<dt><?= $this->toHuman($field['name']) ?></dt>
			<dd>%?= $<?= $singularCamel ?>-><?= $field['name'] ?> ? $<?= $singularCamel ?>-><?= $field['name'] ?>->format('d/m/Y') : '-' ?></dd>
<?php elseif($field['type'] === 'datetime'): ?>
			<dt><?= $this->toHuman($field['name']) ?></dt>
			<dd>%?= $<?= $singularCamel ?>-><?= $field['name'] ?> ? $<?= $singularCamel ?>-><?= $field['name'] ?>->format('d/m/Y H:i') : '-' ?></dd>
<?php elseif($field['type'] === 'array'): ?>
			<dt><?= $this->toHuman($field['name']) ?></dt>
			<dd>%?= !empty($<?= $singularCamel ?>-><?= $field['name'] ?>) ? implode(', ', array_map('e', $<?= $singularCamel ?>-><?= $field['name'] ?>)) : '-' ?></dd>
<?php else: ?>
			<dt><?= $this->toHuman($field['name']) ?></dt>
			<dd>%?= e($<?= $singularCamel ?>-><?= $field['name'] ?>) ?></dd>
<?php endif; ?>
<?php endforeach; ?>
			<dt>created</dt>
			<dd>%?= $<?= $singularCamel ?>->created_at?->format('d/m/Y H:i') ?></dd>
			<dt>updated</dt>
			<dd>%?= $<?= $singularCamel ?>->updated_at?->format('d/m/Y H:i') ?></dd>
		</dl>
	</div>
</section>
