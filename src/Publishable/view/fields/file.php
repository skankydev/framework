<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>"  <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
	<?php endif; ?>

	<?php // a file input can't be pre-filled: show the current file instead (edit form) ?>
	<?php if ($value instanceof \SkankyDev\Model\Document\StoredFile && $value->path): ?>
		<div class="form-file-current">
			<?php if ($value->isImage()): ?>
				<img src="<?= e($value->url()) ?>" alt="" style="max-width: 160px; max-height: 160px;">
			<?php endif; ?>
			<a href="<?= e($value->url()) ?>" target="_blank"><?= e($value->original_name ?: basename($value->path)) ?></a>
		</div>
	<?php endif; ?>

	<input 
		type="file" 
		id="<?= $id ?>" 
		name="<?= $name ?>" 
		<?= $this->required() ?>
		<?= $this->createAttr($attributes) ?>
	>
	
	<?php if ($errors): ?>
		<div class="text-error"><?= e($errors[0]) ?></div>
	<?php endif; ?>
</div>
