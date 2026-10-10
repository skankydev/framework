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
use SkankyDev\Form\Fields\FileField;
use SkankyDev\Http\Request;
use SkankyDev\Http\UrlBuilder;
use SkankyDev\Utilities\Traits\HtmlHelper;
use SkankyDev\Utilities\Traits\StringFacility;
use SkankyDev\Validation\Validator;
use SkankyDev\Utilities\Session;

abstract class FormBuilder {

	use HtmlHelper, StringFacility;
	
	protected string $action = '';
	protected string $method = 'POST';
	protected array $attributes = [];
	protected array $fields = [];
	protected array $fieldTypes = [];
	protected ?string $submitLabel = 'save';
	protected array $submitAttributes = ['type'=>'submit','class'=>'btn btn-primary'];
	protected array $data = [];
	protected array $errors = [];
	protected array $old = [];

	/**
	 * @param array  $link       route link array passed to UrlBuilder to build the form action
	 * @param string $method     HTTP method, defaults to POST
	 * @param array  $attributes extra HTML attributes on the <form> tag
	 */
	public function __construct(array|string $link = [], string $method = 'POST', array $attributes = []) {
		if(is_array($link)){
			$this->action = UrlBuilder::_build($link);
		}else{
			$this->action = $link;
		}

		$this->method = strtoupper($method);
		$defaultAttributes = ['id'=>'AppForm'];
		$this->attributes =[...$defaultAttributes, ...$attributes];
		$this->fieldTypes = Config::get('class.fields');
		$this->errors = Session::getAndClean('errors') ?? [];
		$this->old = Session::getAndClean('old') ?? [];
		
	}
	
	/**
	 * Defines the form fields. Must be implemented in concrete form classes.
	 */
	abstract public function build(): void;

	/**
	 * Adds a field to the form.
	 * Pre-fills the value from old input (flash) or from setData(), in that order.
	 * Attaches validation errors if any exist for this field name.
	 * @param string $name    field name, used as HTML name attribute and error key
	 * @param string $type    field type key as declared in config `class.fields`
	 * @param array  $options field options (label, rules, value, attributes, etc.)
	 * @throws \Exception if the field type is not registered
	 */
	public function add(string $name, string $type, array $options = []): self {
		if (!isset($this->fieldTypes[$type])) {
			throw new \Exception("Unknown field type: {$type}");
		}
		
		$fieldClass = $this->fieldTypes[$type];

		if (isset($this->old[$name])) {
			$options['value'] = $this->old[$name];
		}else if (isset($this->data[$name])) {
			$options['value'] = $this->data[$name];
		}
		
		// Ajouter les erreurs si disponibles
		if (isset($this->errors[$name])) {
			$options['errors'] = (array) $this->errors[$name];
		}
		
		$this->fields[$name] = new $fieldClass($name, $options);
		return $this;
	}
	
	/**
	 * Sets the submit button label and optional extra attributes.
	 */
	public function submit(string $label, array $attributes = []): self {
		$this->submitLabel = $label;
		$this->submitAttributes = [...$this->submitAttributes , ...$attributes];
		return $this;
	}
	
	/**
	 * Pre-fills the form with existing data (e.g. a Document for an edit form).
	 * Accepts an object — properties are extracted via get_object_vars().
	 */
	public function setData(array|object $data): self {
		if(is_object($data)){
			$data = get_object_vars($data);
		}
		$this->data = $data;
		
		// Mettre à jour les valeurs des champs existants
		/*foreach ($this->fields as $name => $field) {
			if (isset($data[$name])) {
				$field->setValue($data[$name]);
			}
		}*/
		
		return $this;
	}
	
	/**
	 * Sets validation errors on the form and propagates them to the corresponding fields.
	 */
	public function setErrors(array $errors): self {
		$this->errors = $errors;
		
		// Mettre à jour les erreurs des champs existants
		foreach ($this->fields as $name => $field) {
			if (isset($errors[$name])) {
				$field->setErrors((array) $errors[$name]);
			}
		}
		
		return $this;
	}
	
	/**
	 * Renders the full form: opening tag, all fields, submit button and closing tag.
	 */
	public function render(): string {
		$html = $this->open();
		
		foreach ($this->fields as $field) {
			$html .= $field->render();
		}
		
		if ($this->submitLabel) {
			$html .= $this->renderSubmit();
		}
		
		$html .= $this->close();
		
		return $html;
	}
	
	/**
	 * Renders the opening <form> tag, calls build() if fields are not yet initialized,
	 * and injects a CSRF token for POST forms.
	 */
	public function open(): string {
		if(empty($this->fields)){
			$this->build();
		}

		$attributes = $this->attributes;
		// without multipart the browser silently sends no file at all
		if (!isset($attributes['enctype']) && $this->hasFileField()) {
			$attributes['enctype'] = 'multipart/form-data';
		}

		$html = '<form action="' . $this->action . '" method="' . $this->method . '" ';
		$html .= $this->createAttr($attributes);
		$html .= '>';
		
		// Ajouter le CSRF token si méthode POST
		if ($this->method === 'POST') {
			$html .= csrf_field();
		}
		
		return $html;
	}
	
	/**
	 * Renders the closing </form> tag.
	 */
	public function close(): string {
		return '</form>';
	}
	
	/**
	 * Renders a single field by name, useful for custom form layouts.
	 * @throws \Exception if the field name is not registered
	 */
	public function renderField(string $name): string {
		if (!isset($this->fields[$name])) {
			throw new \Exception("Field not found: {$name}");
		}
		
		return $this->fields[$name]->render();
	}
	
	/**
	 * Renders the submit button via src_front/view/fields/submit.php — un
	 * template dédié (comme les autres champs) plutôt qu'une concaténation
	 * de string, pour permettre de structurer le label (icône + span, etc.)
	 * sans bricoler du HTML brut dans le label.
	 */
	protected function renderSubmit(): string {
		// Publishable d'abord (défaut framework), sinon view.folder classique
		// (permet à un projet de déposer son propre fields/submit.php sans
		// toucher à la config) — même logique que FormField::makePath().
		$path = Config::get('view.fields') . DS . 'submit.php';
		if (!file_exists($path)) {
			$path = Config::get('view.folder') . DS . 'fields' . DS . 'submit.php';
		}

		return $this->renderFile($path, [
			'label'      => $this->submitLabel,
			'attributes' => $this->submitAttributes,
		]);
	}

	/**
	 * Includes a template inside a closure so only `$this` and the extracted
	 * `$data` are visible to it. Output buffers left open by the template are
	 * closed if it throws.
	 */
	private function renderFile(string $file, array $data): string {
		$level = ob_get_level();
		ob_start();
		try {
			(function (string $myFile, array $myData) {
				extract($myData);
				require $myFile;
			})($file, $data);
			return ob_get_clean();
		} catch (\Throwable $e) {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}
			throw $e;
		}
	}

	/** True when at least one field is a FileField (or a subclass). */
	public function hasFileField(): bool {
		if (empty($this->fields)) {
			$this->build();
		}
		foreach ($this->fields as $field) {
			if ($field instanceof FileField) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns all registered field instances.
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * Filtre un tableau de données pour ne garder que les clés déclarées comme champs du form.
	 * Sert à éviter le mass assignment (ex: `new User($form->only($input))`) quand $input
	 * vient directement de la requête et peut contenir des clés en plus des champs attendus.
	 * Appelle build() si les champs ne sont pas encore initialisés.
	 */
	public function only(array $data): array {
		if (empty($this->fields)) {
			$this->build();
		}
		return array_intersect_key($data, $this->fields);
	}

	/**
	 * Validates submitted data against the rules defined in each field.
	 * Calls build() if fields are not yet initialized.
	 * Populates errors on the form and its fields on failure.
	 * N'appelle pas setData($data) : les données du form restent celles posées
	 * explicitement (ex: setData($user) en édition) — l'input soumis part de
	 * toute façon en session via withInput() en cas d'échec, pas depuis ce $form.
	 * Uploaded files are not part of Request::input(): for every file field missing
	 * from $data, the UploadedFile is taken from Request::file() so rules like
	 * `image` or `max_size` see it — and $data stays file-free for withInput().
	 * @param array $data raw input data (typically from Request::input())
	 */
	public function validate(array $data): bool {
		if(empty($this->fields)){
			$this->build();
		}

		foreach ($this->fields as $name => $field) {
			if ($field instanceof FileField && !array_key_exists($name, $data)) {
				$data[$name] = Request::getInstance()->file($name);
			}
		}

		// Récupérer les règles depuis les champs
		$rules = [];
		foreach ($this->fields as $name => $field) {
			$fieldRules = $field->getRules();
			if (!empty($fieldRules)) {
				$rules[$name] = $fieldRules;
			}
		}
		
		
		$validator = new Validator($rules, $data);
		
		if (!$validator->validate()) {
			$this->setErrors($validator->errors());
			return false;
		}
		
		return true;
		
	}
	
	/**
	 * Returns the data currently set on the form.
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Returns the validation errors indexed by field name.
	 */
	public function getErrors(): array {
		return $this->errors;
	}
}