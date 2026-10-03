<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>" <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
	<?php endif; ?>
	<textarea name="<?= $name ?>" id="<?= $id ?>" <?= $this->createAttr($attributes) ?> <?= $this->required() ?>><?= ($value) ?></textarea>
	<?php if ($errors): ?>
		<span class="text-error"><?= e($errors[0]) ?></span>
	<?php endif; ?>
</div>