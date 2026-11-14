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

/**
 * Renders any value print_r style, coloured: HTML (the `debug-*` CSS classes) for a
 * page, ANSI escape codes for a terminal.
 * The output is bounded by construction: depth, items per array/object, string
 * length and total number of values are all capped, so dumping a huge graph
 * (a paginator full of documents, a stack trace argument…) can never exhaust
 * memory. Whatever is cut is replaced by a visible `…` marker.
 */
class Dumper {

	/** Default limits, overridable per call (see html()). */
	public const LIMITS = [
		'depth'  => 6,     // nesting levels rendered
		'items'  => 100,   // entries rendered per array / object
		'string' => 2000,  // characters rendered per string
		'nodes'  => 5000,  // values rendered in total
	];

	private const INDENT = '    ';

	/** ANSI colour of each `debug-*` CSS class, for the terminal rendering. */
	private const ANSI = [
		'var'     => '33',        // yellow
		'keyword' => '36',        // cyan
		'integer' => '38;5;208',  // orange
		'source'  => '32',        // green
		'text'    => '92',        // bright green
		'php'     => '91',        // bright red
	];

	/** Forced output mode (`html` / `cli`), null = detected from the SAPI. See forceMode(). */
	private static ?string $mode = null;

	private int $nodes = 0;

	/** @var array<int,bool> ids of the objects being rendered (recursion guard) */
	private array $stack = [];

	private function __construct(private array $limits, private bool $ansi = false) { }

	/**
	 * Returns the HTML dump of $data (no wrapper: meant to be placed in a `<pre>`).
	 * @param array $limits overrides of self::LIMITS (`depth`, `items`, `string`, `nodes`)
	 */
	public static function html(mixed $data, array $limits = []): string {
		return (new self($limits + self::LIMITS))->render($data, 0);
	}

	/**
	 * Returns the terminal dump of $data: same layout and limits as html(),
	 * coloured with ANSI escape codes instead of `<span>`.
	 */
	public static function cli(mixed $data, array $limits = []): string {
		return (new self($limits + self::LIMITS, true))->render($data, 0);
	}

	/** True when the output goes to a terminal (CLI SAPI, unless a mode is forced). */
	public static function isCli(): bool {
		return self::$mode !== null ? self::$mode === 'cli' : PHP_SAPI === 'cli';
	}

	/**
	 * Forces the output mode of debug() (`html` or `cli`), null to go back to the
	 * detection. For the cases where the SAPI lies: tests, a CLI script building HTML…
	 */
	public static function forceMode(?string $mode): void {
		self::$mode = $mode;
	}

	/** Wraps $text in the colour of a `debug-*` class, for the current output (terminal or HTML). */
	public static function colour(string $class, string $text): string {
		return self::isCli()
			? "\033[" . self::ANSI[$class] . 'm' . $text . "\033[0m"
			: '<span class="debug-' . $class . '">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
	}

	private function render(mixed $value, int $depth): string {
		$this->nodes++;

		return match (true) {
			$value === null     => $this->span('keyword', 'null'),
			is_bool($value)     => $this->span('keyword', $value ? 'true' : 'false'),
			is_int($value),
			is_float($value)    => $this->span('integer', (string) $value),
			is_string($value)   => $this->renderString($value),
			is_array($value)    => $this->renderList('Array', $value, count($value), $depth),
			is_object($value)   => $this->renderObject($value, $depth),
			default             => $this->span('source', get_debug_type($value)),
		};
	}

	private function renderString(string $value): string {
		$length = mb_strlen($value);
		$max = $this->limits['string'];
		if ($length <= $max) {
			return $this->span('text', $value);
		}
		return $this->span('text', mb_substr($value, 0, $max))
			. $this->cut('… +' . ($length - $max) . ' chars');
	}

	private function renderObject(object $object, int $depth): string {
		$class = get_class($object);

		// Values that read better as a single line than as a property list
		if ($object instanceof \UnitEnum) {
			return $this->span('source', $class) . '::' . $this->span('var', $object->name);
		}
		if ($object instanceof \DateTimeInterface) {
			return $this->span('source', $class) . ' ' . $this->span('text', $object->format('Y-m-d H:i:s.u P'));
		}
		if ($object instanceof \Closure) {
			$function = new \ReflectionFunction($object);
			return $this->span('source', 'Closure') . ' ' . $this->span('text', $function->getFileName() . ':' . $function->getStartLine());
		}
		if ($object instanceof \MongoDB\BSON\Type && $object instanceof \Stringable) {
			return $this->span('source', $class) . ' ' . $this->span('text', (string) $object);
		}

		$id = spl_object_id($object);
		if (isset($this->stack[$id])) {
			return $this->span('source', $class) . ' ' . $this->cut('*RECURSION*');
		}

		// (array) exposes every property, private ones included, under mangled names:
		// "\0Class\0name" (private) and "\0*\0name" (protected)
		$properties = [];
		foreach ((array) $object as $name => $value) {
			$parts = explode("\0", (string) $name);
			if (count($parts) === 3) {
				$name = $parts[2] . ':' . ($parts[1] === '*' ? 'protected' : 'private');
			}
			$properties[$name] = $value;
		}

		$this->stack[$id] = true;
		$html = $this->renderList($class, $properties, count($properties), $depth);
		unset($this->stack[$id]);

		return $html;
	}

	/**
	 * Renders `Label (count) ( [key] => value … )`, each entry on its own line.
	 */
	private function renderList(string $label, array $entries, int $count, int $depth): string {
		$head = $this->span('source', $label) . ' (' . $this->span('integer', (string) $count) . ')';
		if ($count === 0) {
			return $head . ' ()';
		}
		if ($depth >= $this->limits['depth']) {
			return $head . ' ( ' . $this->cut('…') . ' )';
		}

		$pad = str_repeat(self::INDENT, $depth + 1);
		$html = $head . " (\n";
		$shown = 0;
		foreach ($entries as $key => $value) {
			if ($shown >= $this->limits['items'] || $this->nodes >= $this->limits['nodes']) {
				$html .= $pad . $this->cut('… +' . ($count - $shown) . ' more') . "\n";
				break;
			}
			$html .= $pad . '[' . $this->span('var', (string) $key) . '] => ' . $this->render($value, $depth + 1) . "\n";
			$shown++;
		}

		return $html . str_repeat(self::INDENT, $depth) . ')';
	}

	private function span(string $class, string $text): string {
		if ($this->ansi) {
			return "\033[" . self::ANSI[$class] . 'm' . $text . "\033[0m";
		}
		return '<span class="debug-' . $class . '">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
	}

	/** Marker for whatever a limit left out. */
	private function cut(string $text): string {
		return $this->span('php', $text);
	}
}
