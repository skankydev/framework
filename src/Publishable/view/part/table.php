<?php
/**
 * Table générique data-driven.
 * Pilotée par $paginator->getDisplayField() (défini sur la Collection).
 *
 * @var \SkankyDev\Utilities\Paginator $paginator  liste paginée + définition des colonnes
 * @var bool          $btnShow  affiche le bouton "voir" (défaut true)
 * @var bool          $btnEdit  affiche le bouton "éditer" (défaut true)
 * @var bool          $paginBtn affiche le paginator en pied de table (défaut true)
 * @var string        $trAction action ciblée par le clic sur une ligne (défaut 'show')
 * @var callable|null $actions  fn($document): string → boutons d'action supplémentaires
 *
 * Par colonne (getDisplayField) : 'label', 'sort', et callbacks optionnels
 * 'render' (remplace la cellule), 'before'/'after' (ajoutent avant/après la valeur).
 */
$btnShow  = $btnShow ?? true;
$btnEdit  = $btnEdit ?? true;
$btnDelete  = $btnDelete ?? false;
$actions  = $actions ?? null;
$paginBtn = $paginBtn ?? true;
$trAction = $trAction ?? 'show';

$fields     = $paginator->getDisplayField();
$controller = $paginator->controller();
$singular   = $paginator->singular();
$hasActions = $btnShow || $btnEdit || $actions;
?>
<table>
	<thead>
		<tr>
			<?php foreach ($fields as $field => $def): ?>
				<th><?= !empty($def['sort']) ? $paginator->sortLink($field, $def['label']) : e($def['label']) ?></th>
			<?php endforeach; ?>
			<?php if ($hasActions): ?><th class="action"></th><?php endif; ?>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($paginator as $document): ?>

		<tr class="clickable-row" data-url="<?= $this->url(['controller' => $controller, 'action' => $trAction, 'params' => [$singular => $document->_id]]) ?>">
			<?php foreach ($fields as $field => $def): ?>
			<td><?php
				if (isset($def['before'])) {
					echo $def['before']($document);
				}

				echo isset($def['render']) ? $def['render']($document) : $paginator->cellValue($document, $field);

				if (isset($def['after'])) {
					echo $def['after']($document);
				}
			?></td>
			<?php endforeach; ?>
			<?php if ($hasActions): ?>
			<td class="action">
				<?php if ($btnShow): ?>
					<?= $this->link('<i class="icon-info"></i>', ['controller' => $controller, 'action' => 'show', 'params' => [$singular => $document->_id]], ['class' => 'btn-mini btn-info','data-tooltip'=>'Show']) ?>
				<?php endif; ?>
				<?php if ($btnEdit): ?>
					<?= $this->link('<i class="icon-edit-2"></i>', ['controller' => $controller, 'action' => 'edit', 'params' => [$singular => $document->_id]], ['class' => 'btn-mini btn-warning','data-tooltip'=>'Edit']) ?>
				<?php endif; ?>
				<?php if ($btnDelete): ?>
					<?= $this->link('<i class="icon-trash"></i>', ['controller' => $controller, 'action' => 'edit', 'params' => [$singular => $document->_id]], ['class' => 'btn-mini btn-error','data-tooltip'=>'Delete','data-method'=>'post']) ?>
				<?php endif; ?>
				<?php if ($actions): ?>
					<?= $actions($document) ?>
				<?php endif; ?>
			</td>
			<?php endif; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php if ($paginBtn): ?>
<?= $this->part('part.paginator', $paginator->getOption()) ?>
<?php endif ?>
