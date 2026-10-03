<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<input type="hidden" name="<?= $name ?>"  value="0">
	<div class="checkbox-item">
		<input
			type="<?= $type ?>"
			id="<?= $id ?>"
			name="<?= $name ?>"
			value="1"
			<?= $this->required() ?>
			<?= $this->createAttr($attributes) ?>
		>

		<?php if ($label): ?>
			<label for="<?= $id ?>"  <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
		<?php endif; ?>
	</div>

	<?php if ($errors): ?>
		<span class="text-error"><?= e($errors[0]) ?></span>
	<?php endif; ?>
</div>