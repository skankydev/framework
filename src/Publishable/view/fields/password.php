<div class="form-group password-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>"  <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
	<?php endif; ?>

	<div class="password-input-wrapper">
		<input
			type="password"
			id="<?= $id ?>"
			name="<?= $name ?>"
			value=""
			data-password-input
			<?= $this->required() ?>
			<?= $this->createAttr($attributes) ?>
		>
		<div class="password-toggle">
			<div class="btn-mini" data-password-toggle aria-label="Afficher le mot de passe">
				<i class="icon-eye"></i>
			</div>
		</div>
	</div>

	<?php if ($guide): ?>
	<div class="password-strength" data-password-strength>
		<div class="password-strength-label" data-password-strength-label></div>
		<div class="password-strength-bar">
			<span class="password-strength-segment"></span>
			<span class="password-strength-segment"></span>
			<span class="password-strength-segment"></span>
		</div>
	</div>

	<div class="password-hint">Votre mot de passe doit contenir des lettres majuscules et minuscules, ainsi que des chiffres et des caractères spéciaux.</div>
	<?php endif ?>

	<?php if ($errors): ?>
		<div class="text-error"><?= e($errors[0]) ?></div>
	<?php endif; ?>
</div>
