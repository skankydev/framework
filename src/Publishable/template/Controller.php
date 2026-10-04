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
	 * Liste paginée des <?= $pluralCamel ?>.
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
	 * Affiche le formulaire de création d'un <?= $singularCamel ?>.
	 */
	public function create(){
		$form = new <?= $name ?>Form(['action' => 'store']);
		return view('<?= $viewPath ?>.create', ['form' => $form]);
	}

	/**
	 * Valide et enregistre un nouveau <?= $singularCamel ?>, puis redirige vers son show.
	 * En cas d'échec de validation, retourne au formulaire avec erreurs et anciennes valeurs.
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
		return redirect(['action' => 'show', 'params' => [$<?= $singularCamel ?>->_id]])->withFlash('success', 'Enregistrement réussi');
	}

	/**
	 * Affiche un <?= $singularCamel ?> (résolu par model binding depuis l'ID de l'URL).
	 */
	public function show(Request $request, <?= $name ?> $<?= $singularCamel ?>){
		return view('<?= $viewPath ?>.show', ['<?= $singularCamel ?>' => $<?= $singularCamel ?>]);
	}

	/**
	 * Affiche le formulaire d'édition d'un <?= $singularCamel ?>, pré-rempli avec ses données.
	 */
	public function edit(<?= $name ?> $<?= $singularCamel ?>){
		$form = new <?= $name ?>Form(['action' => 'update', 'params' => [$<?= $singularCamel ?>->_id]]);
		$form->setData($<?= $singularCamel ?>);
		return view('<?= $viewPath ?>.edit', ['form' => $form, '<?= $singularCamel ?>' => $<?= $singularCamel ?>]);
	}

	/**
	 * Valide et met à jour un <?= $singularCamel ?> existant, puis redirige vers son show.
	 * En cas d'échec de validation, retourne au formulaire avec erreurs et anciennes valeurs.
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
		return redirect(['action' => 'show', 'params' => [$<?= $singularCamel ?>->_id]])->withFlash('success', 'Modification réussie');
	}

	/**
	 * Supprime un <?= $singularCamel ?> puis redirige vers la liste.
	 */
	#[Middleware('PostOnly')]
	public function delete(<?= $name ?> $<?= $singularCamel ?>){
		<?= $name ?>Collection::_deleteOne($<?= $singularCamel ?>);
		return redirect(['action' => 'index'])->withFlash('success', 'Suppression réussie');
	}
}
