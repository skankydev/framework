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


class RadioField extends FormField {
	protected string $type = 'radio';
	protected string $viewHtml = 'fields.radio';
	protected array $options = [];

	
	public function __construct(string $name, array $options = []) {
		parent::__construct($name, $options);
		
		// Stocker les options du radio
		$this->options = $options['options'] ?? [];
	}
}