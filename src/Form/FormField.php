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

namespace SkankyDev\Form;

use SkankyDev\Config\Config;
use SkankyDev\Utilities\Traits\HtmlHelper;
use SkankyDev\Utilities\Traits\StringFacility;

abstract class FormField {
	
	use HtmlHelper, StringFacility;

	protected string $viewHtml = 'fields.default';
	protected string $type = 'text';
	protected string $name;
	protected ?string $label = null;
	protected mixed $value = null;
	protected array $labelAttr = [];
	protected array $attributes = [];
	protected array $rules = [];
	protected array $errors = [];
	protected string $id = '';

	protected array $defaults=[
		'attributes' => ['class'=>'form-input'],
		'labelAttributes' =>  ['class'=>'form-label'],
	];
	
	/**
	 * @param string $name    HTML name attribute of the field
	 * @param array  $options label, value, default, rules, attributes, labelAttributes, id, errors
	 */
	public function __construct(string $name, array $options = []) {

		$options = [...$this->defaults, ...$options];

		$this->name = $name;
		$this->label = $options['label'] ?? $name;
		$this->value = $options['value'] ?? $options['default'] ?? null;
		$this->attributes = $options['attributes'] ?? [];
		$this->labelAttr = $options['labelAttributes'] ?? [];
		$this->rules = $options['rules'] ?? [];
		$this->id = $options['id'] ?? $this->toCap($name,'_');
		$this->errors = $options['errors'] ?? [];
	}
	
	/**
	 * Resolves a dot-notation view name to an absolute file path.
	 * Checks the Publishable fields folder first (`view.fields`, cf.
	 * default.config.php) — the fields shipped by the framework live there
	 * by default. Falls back to the classic `view.folder` resolution for
	 * app-specific field types (ex: icon, editorjs), which never live in
	 * Publishable.
	 * @throws \Exception if the template file does not exist
	 */
	public function makePath(string $name): string {
		$parts = explode('.', $name);
		$fieldName = end($parts);

		$publishablePath = Config::get('view.fields') . DS . $fieldName . '.php';
		if (file_exists($publishablePath)) {
			return $publishablePath;
		}

		$folderName = $this->dotToFolder($name);
		$fileName = Config::get('view.folder').DS.$folderName.'.php';
		if(!file_exists($fileName)){
			throw new \Exception("the file : {$fileName} does not exist", 601);
		}
		return $fileName;
	}
	
	/**
	 * Renders the field by including its PHP template.
	 * Exposes all field properties to the template via extract().
	 */
	public function render(): string {
		if(isset($this->labelAttr['class'])){
			$this->labelAttr['class'] .= ' '.$this->required();
		}
		$path = $this->makePath($this->viewHtml);
		$data = get_object_vars($this);
		extract($data);
		ob_start();
		require $path;
		return ob_get_clean();
	}
	
	public function setValue(mixed $value): self {
		$this->value = $value;
		return $this;
	}
	
	public function getValue(): mixed {
		return $this->value;
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function setErrors(array $errors): self {
		$this->errors = $errors;
		return $this;
	}
	
	public function getErrors(): array {
		return $this->errors;
	}
	
	public function hasErrors(): bool {
		return !empty($this->errors);
	}
	
	public function getFirstError(): ?string {
		return $this->errors[0] ?? null;
	}
	
	public function getRules(): array {
		return $this->rules;
	}

	/**
	 * Returns true if the `required` rule is present on this field.
	 */
	public function isRequired(): bool {
		return in_array('required', $this->rules);
	}

	/**
	 * Returns the string `'required'` if the field is required, empty string otherwise.
	 * Used to append a CSS class to the label.
	 */
	public function required(): string {
		return $this->isRequired() ? 'required' : '' ;
	}

}