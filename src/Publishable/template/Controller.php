%?php
/**
 * Copyright (c) 2025 SCHENCK Simon
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright     Copyright (c) SCHENCK Simon
 *
 */

namespace <?= $module ?>\Controller;

use <?= $module ?>\Form\<?= $name ?>Form;
use <?= $module ?>\Model\Document\<?= $name ?>;
use <?= $module ?>\Model\<?= $name ?>Collection;
use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Middleware\Attribute\Middleware;
use SkankyDev\Http\Request;

class <?= $name ?>Controller extends MasterController {

	/**
	 * Paginated list of <?= $pluralCamel ?>.
	 */
	public function index(<?= $name ?>Collection $collection){
<?php $relationNames = array_map(fn($f) => "'" . lcfirst($this->fkRelated($f['name'])) . "'", array_filter($this->fields, fn($f) => $f['type'] === 'ObjectId')); ?>
<?php if(!empty($relationNames)): ?>
		$<?= $pluralCamel ?> = $collection->paginate([], Request::_paginateInfo(), [<?= implode(', ', $relationNames) ?>]);
<?php else: ?>
		$<?= $pluralCamel ?> = $collection->paginate([], Request::_paginateInfo());
<?php endif; ?>
		return view('<?= $viewPath ?>.index', ['<?= $pluralCamel ?>' => $<?= $pluralCamel ?>]);
	}

	/**
	 * Shows the creation form for a <?= $singularCamel ?>.
	 */
	public function create(){
		$form = new <?= $name ?>Form(['action' => 'store']);
		return view('<?= $viewPath ?>.create', ['form' => $form]);
	}

	/**
	 * Validates and saves a new <?= $singularCamel ?>, then redirects to its show page.
	 * On validation failure, goes back to the form with errors and old input.
	 */
	#[Middleware('PostOnly')]
	public function store(Request $request){
		$input = $request->input();
		$form = new <?= $name ?>Form(['action' => 'store']);
		if(!$form->validate($input)){
			return redirect(['action' => 'create'])->withErrors($form->getErrors())->withInput($input);
		}
		$<?= $singularCamel ?> = new <?= $name ?>($form->only($input));
		<?= $name ?>Collection::_save($<?= $singularCamel ?>);
		return redirect(['action' => 'show', 'params' => [$<?= $singularCamel ?>->_id]])->withFlash('success', 'Successfully created');
	}

	/**
	 * Shows a <?= $singularCamel ?> (resolved by model binding from the URL ID).
	 */
	public function show(Request $request, <?= $name ?> $<?= $singularCamel ?>){
		return view('<?= $viewPath ?>.show', ['<?= $singularCamel ?>' => $<?= $singularCamel ?>]);
	}

	/**
	 * Shows the edit form for a <?= $singularCamel ?>, pre-filled with its data.
	 */
	public function edit(<?= $name ?> $<?= $singularCamel ?>){
		$form = new <?= $name ?>Form(['action' => 'update', 'params' => [$<?= $singularCamel ?>->_id]]);
		$form->setData($<?= $singularCamel ?>);
		return view('<?= $viewPath ?>.edit', ['form' => $form, '<?= $singularCamel ?>' => $<?= $singularCamel ?>]);
	}

	/**
	 * Validates and updates an existing <?= $singularCamel ?>, then redirects to its show page.
	 * On validation failure, goes back to the form with errors and old input.
	 */
	#[Middleware('PostOnly')]
	public function update(Request $request, <?= $name ?> $<?= $singularCamel ?>){
		$input = $request->input();
		$form = new <?= $name ?>Form(['action' => 'update', 'params' => [$<?= $singularCamel ?>->_id]]);
		$form->setData($<?= $singularCamel ?>);
		if(!$form->validate($input)){
			return redirect(['action' => 'edit', 'params' => [$<?= $singularCamel ?>->_id]])->withErrors($form->getErrors())->withInput($input);
		}
		$<?= $singularCamel ?>->fill($form->only($input));
		<?= $name ?>Collection::_save($<?= $singularCamel ?>);
		return redirect(['action' => 'show', 'params' => [$<?= $singularCamel ?>->_id]])->withFlash('success', 'Successfully updated');
	}

	/**
	 * Deletes a <?= $singularCamel ?> then redirects to the list.
	 */
	#[Middleware('PostOnly')]
	public function delete(<?= $name ?> $<?= $singularCamel ?>){
		<?= $name ?>Collection::_deleteOne($<?= $singularCamel ?>);
		return redirect(['action' => 'index'])->withFlash('success', 'Successfully deleted');
	}
}
