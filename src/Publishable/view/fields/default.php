<?php
// Un DateTime doit être formaté dans le format attendu par l'input HTML,
// sinon l'echo plante (pas de __toString) et la value n'est pas reconnue.
$displayValue = $value;
if ($value instanceof \DateTime) {
	$displayValue = $value->format($type === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
}
?>
<div class="form-group <?= $errors ? 'has-error' : '' ?>">
	<?php if ($label): ?>
		<label for="<?= $id ?>"  <?= $this->createAttr($labelAttr) ?> ><?= e($label) ?></label>
	<?php endif; ?>

	<input
		type="<?= $type ?>"
		id="<?= $id ?>"
		name="<?= $name ?>"
		value="<?= e($displayValue) ?>"
		<?= $this->required() ?>
		<?= $this->createAttr($attributes) ?>
	>
	
	<?php if ($errors): ?>
		<div class="text-error"><?= e($errors[0]) ?></div>
	<?php endif; ?>
</div>