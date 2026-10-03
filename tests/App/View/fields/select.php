<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>" <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
	<?php endif; ?>
	
	<select
		id="<?= $id ?>" 
		name="<?= $name ?>" 
		<?= $this->required() ?>
	>	
		<?php if ($empty): ?>
			<option value=""><?= $empty ?></option>
		<?php endif ?>
		<?php foreach ($options as $key => $display): ?>
			<option value="<?= $key ?>" <?= $key == $value ? 'selected' : '' ?>><?= $display ?></option>
		<?php endforeach ?>
	</select>

	<?php if ($errors): ?>
		<span class="text-error"><?= e($errors[0]) ?></span>
	<?php endif; ?>
</div>