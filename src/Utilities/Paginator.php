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

namespace SkankyDev\Utilities;

use SkankyDev\Utilities\Traits\IterableData;
use SkankyDev\Utilities\Traits\HtmlHelper;
use SkankyDev\Config\Config;
use Iterator;

/**
 * Wraps a MongoDB cursor result set with pagination metadata.
 * Implements Iterator so it can be used directly in foreach loops.
 */
class Paginator implements Iterator {

	use IterableData, HtmlHelper;

	public array $data   = [];
	public array $option = ['sort' => ['_id' => -1]];

	/** Base route link reused by all the links this paginator generates (sort + pages). */
	protected array $baseLink = [];

	/** Column definitions for the generic table part (from Collection::getDisplayField). */
	protected array $displayField = [];

	/** FQ document class name — used to derive the resource controller / id param. */
	protected string $documentClass = '';

	/**
	 * @param iterable $data   the result set (MongoDB cursor or array)
	 * @param array    $option pagination options: page, limit, total, range, sort
	 */
	public function __construct(iterable $data, array $option) {
		$this->data   = $data;
		$this->option = array_merge($this->option, $option);
	}

	/**
	 * Finalises pagination info and attaches the link/get arrays used by the view helper.
	 * @param array $link  route array passed to UrlBuilder (controller, action, params…)
	 * @param array $get   extra GET parameters to append to pagination links
	 * @return array       the complete option array ready for the view
	 */
	public function getOption(array $link = [], array $get = []): array {
		$this->initInfo();
		$this->option['link']    = !empty($link) ? $link : $this->baseLink;
		$this->option['get']     = $get;
		$this->option['sortGet'] = $this->sortGet();
		return $this->option;
	}

	/**
	 * Sets the base route link reused by every link this paginator builds
	 * (sort headers and page links). Defaults to the current route when left unset.
	 * Call it early in the view for nested lists (e.g. a part inside a `show`).
	 * @param array $link route array (action, params…) passed to UrlBuilder
	 */
	public function setLink(array $link): static {
		$this->baseLink = $link;
		return $this;
	}

	/**
	 * Computes derived pagination values (pages, first, last, next, prev, start, stop).
	 * @param array $option optional overrides merged before computing
	 */
	public function initInfo(array $option = []): void {
		$this->option = array_merge($this->option, $option);
		$this->option['pages'] = (int) floor($this->option['total'] / $this->option['limit'])
			+ (($this->option['total'] % $this->option['limit']) ? 1 : 0);
		$this->option['first'] = 1;
		$this->option['last']  = $this->option['pages'];
		$next = $this->option['page'] + 1;
		$this->option['next'] = ($next > $this->option['last']) ? $this->option['last'] : $next;
		$prev = $this->option['page'] - 1;
		$this->option['prev'] = ($prev < $this->option['first']) ? $this->option['first'] : $prev;
		$start = $this->option['page'] - (int) floor($this->option['range'] / 2);
		$this->option['start'] = ($start < $this->option['first']) ? $this->option['first'] : $start;
		$stop = (int) floor($this->option['range'] / 2) + $this->option['page'] + ($this->option['range'] % 2);
		$this->option['stop'] = ($stop > $this->option['last']) ? ($this->option['last'] + 1) : $stop;
	}

	/**
	 * get param array for sort link
	 * @param  string $field the field
	 * @return array         the params for sort link
	 */
	public function sortParams(string $field){
		$sort = $this->option['sort'];
		$key = array_keys($sort);
		$params['page'] = 1;
		$params['field'] = $field;
		if(in_array($field, $key)){
			$params['order'] = $sort[$field]*-1;
		}else{
			$params['order'] = 1;
		}
		return $params;
	}

	/**
	 * Returns the active sort as GET params (`field`/`order`) so page links can
	 * carry it. Returns [] when the sort is the default stable `_id` — no need to
	 * expose it in the URL.
	 * @return array{field?: string, order?: int}
	 */
	public function sortGet(): array {
		$sort = $this->option['sort'] ?? [];
		if (empty($sort)) {
			return [];
		}
		$field = array_key_first($sort);
		if ($field === '_id') {
			return [];
		}
		return ['field' => $field, 'order' => $sort[$field]];
	}

	/**
	 * Builds a ready-to-print sort link for a column header: toggles the order,
	 * carries the current sort, resets to page 1, and shows a ▲/▼ arrow plus a
	 * `sorted` class on the active column.
	 * @param string $field the document field to sort on
	 * @param string $label the column label shown to the user
	 * @param array  $attr  extra HTML attributes for the <a> tag
	 */
	public function sortLink(string $field, string $label, array $attr = []): string {
		$link = [...$this->baseLink, 'get' => $this->sortParams($field)];

		$content = e($label);
		$sort = $this->option['sort'] ?? [];
		if (array_key_exists($field, $sort)) {
			$attr['class'] = trim(($attr['class'] ?? '') . ' sorted');
			$content .= $sort[$field] == 1 ? ' &#9650;' : ' &#9660;';
		}

		return $this->link($content, $link, $attr);
	}

	/** Sets the column definitions (from Collection::getDisplayField). */
	public function setDisplayField(array $fields): static {
		$this->displayField = $fields;
		return $this;
	}

	/** Returns the column definitions for the generic table part. */
	public function getDisplayField(): array {
		return $this->displayField;
	}

	/** Sets the document class, used to derive the resource controller / id param. */
	public function setDocumentClass(string $class): static {
		$this->documentClass = $class;
		return $this;
	}

	/** Resource controller name derived from the document class, e.g. `Module`. */
	public function controller(): string {
		$parts = explode('\\', $this->documentClass);
		return end($parts) ?: '';
	}

	/** Singular id param name for route links, e.g. `module`. */
	public function singular(): string {
		return lcfirst($this->controller());
	}

	/**
	 * Renders the auto-formatted, escaped value of a field for one document.
	 * Reads `$document->{$field}` (so fake fields resolve via the Document __get),
	 * then formats by runtime type: DateTime → date, bool → icon, array → count.
	 * Plain strings are escaped with e(). Custom rendering is handled by the
	 * `render`/`after` callbacks in the column definition (in the view).
	 */
	public function cellValue(object $document, string $field): string {
		$value = $document->{$field};

		if ($value instanceof \DateTime) {
			return $value->format('d/m/Y H:i');
		}
		if ($value instanceof \BackedEnum) {
			return e(method_exists($value, 'label') ? $value->label() : $value->value);
		}
		if (is_bool($value)) {
			return $value
				? '<i class="icon-check text-success"></i>'
				: '<i class="icon-x text-danger"></i>';
		}
		if (is_array($value)) {
			return (string) count($value);
		}
		if ($value === null) {
			return '';
		}
		return e((string) $value);
	}

}
