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


class SelectField extends FormField {
	protected string $type = 'select';
	protected string $viewHtml = 'fields.select';

	protected array $options = [];
	protected $empty = false;

	/**
	 * @param string $name    field name
	 * @param array  $options supports `options` (key=>label pairs) and `empty` (adds a blank first option)
	 */
	public function __construct(string $name, array $options = []) {
		parent::__construct($name, $options);
		$this->options = $options['options'] ?? [];
		$this->empty = $options['empty'] ?? false;
	}

}