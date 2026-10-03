<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>"><?= e($label) ?></label>
	<?php endif; ?>
	<input
		type="<?= $type ?>"
		id="<?= $id ?>"
		name="<?= $name ?>"
		value="<?= e((string)($value ?? '')) ?>"
		<?= $this->required() ?>
	>
	<?php if ($errors): ?>
		<span class="error"><?= e($errors[0]) ?></span>
	<?php endif; ?>
</div>
