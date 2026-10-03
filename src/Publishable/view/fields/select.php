<?php
// Normalise la valeur courante en scalaire pour la comparaison avec les clés d'options :
// un BackedEnum donne sa ->value, un objet (ObjectId) son __toString.
$current = $value instanceof \BackedEnum ? $value->value : (is_object($value) ? (string) $value : $value);
?>
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
			<option value="<?= e((string) $key) ?>" <?= (string) $key === (string) $current ? 'selected' : '' ?>><?= e($display) ?></option>
		<?php endforeach ?>
	</select>

	<?php if ($errors): ?>
		<span class="text-error"><?= e($errors[0]) ?></span>
	<?php endif; ?>
</div>