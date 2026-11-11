<?php
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

namespace SkankyDev\Form\Fields;

use SkankyDev\Form\FormField;

/**
 * Submit button declared like any other field, so it can be placed with
 * renderField() in a custom layout.
 * It carries no data: the <button> has no `name` attribute (nothing is sent
 * in the POST) and FormBuilder::only() leaves it out.
 */
class SubmitField extends FormField {
	protected string $type = 'submit';
	protected string $viewHtml = 'fields.submit';

	protected array $defaults = [
		'attributes' => [],
		'labelAttributes' => [],
	];

	/**
	 * @param string $name    key of the field in the form (used by renderField)
	 * @param array  $options `label` and `attributes` (merged over the default `btn btn-primary` class)
	 */
	public function __construct(string $name, array $options = []) {
		parent::__construct($name, $options);
		$this->attributes = [
			'class' => 'btn btn-primary',
			...$this->attributes,
			'type'  => 'submit',
		];
	}

}
