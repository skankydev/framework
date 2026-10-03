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

namespace SkankyDev\View;

use SkankyDev\Config\Config;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Utilities\Traits\HtmlHelper;
use SkankyDev\Utilities\Traits\StringFacility;

/**
 * Renders PHP view templates with layout support, output buffering, and view parts.
 * Used as `$this` inside every template file — exposes helpers from HtmlHelper and StringFacility.
 */
class HtmlView {

	use HtmlHelper, StringFacility;

	public string $keywords      = '';
	public string $title         = '';
	public array  $meta          = [];
	public string $css           = '';
	public string $js            = '';
	public string $content       = '';
	public array  $blocks        = [];
	public ?string $layout       = null;
	public array  $breadcrumbInfo = [];

	/** Names of the blocks currently buffering, innermost last (supports nesting). */
	private array $blockStack = [];

	public function __construct(
		protected string $viewName = '',
		protected array  $data     = []
	) {
		$this->layout = Config::get('view.layout');
	}

	/**
	 * Resolves a dot-notation view name to an absolute file path.
	 * @throws \Exception (code 601) if the file does not exist
	 */
	public function makePath(string $name): string {
		$fileName = Config::get('view.folder') . DS . $this->dotToFolder($name) . '.php';
		if (!file_exists($fileName)) {
			throw new \Exception("the file : {$fileName} does not exist", 601);
		}
		return $fileName;
	}

	/** Returns the absolute path for the current view. */
	public function viewPath(): string {
		return $this->makePath($this->viewName);
	}

	/** Returns the absolute path for the current layout. */
	public function layoutPath(): string {
		return $this->makePath($this->layout);
	}

	/**
	 * Renders the view then wraps it in the layout (if set).
	 * The rendered view is available as `$content` inside the layout template.
	 */
	public function render(): string {
		$this->content = $this->renderView($this->viewName, $this->data);

		if ($this->layout) {
			return $this->renderLayout($this->layout, [
				'content' => $this->content,
				...$this->data
			]);
		}

		return $this->content;
	}

	/** Renders a view template in isolation and returns its output. */
	protected function renderView(string $view, array $data): string {
		return $this->renderFile($this->makePath($view), $data);
	}

	/** Renders a layout template in isolation and returns its output. */
	protected function renderLayout(string $layout, array $data): string {
		return $this->renderFile($this->makePath($layout), $data);
	}

	/**
	 * Includes a template inside a closure so only `$this` and the extracted
	 * `$data` are visible to it. Output buffers left open by the template (or by
	 * an unfinished startBlock()) are closed if it throws.
	 */
	private function renderFile(string $file, array $data): string {
		$level  = ob_get_level();
		$blocks = count($this->blockStack);
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
			array_splice($this->blockStack, $blocks);
			throw $e;
		}
	}

	/** Overrides the layout. Pass null to render without a layout. */
	public function setLayout(?string $layout): self {
		$this->layout = $layout;
		return $this;
	}

	/**
	 * Renders a view part (sub-template) and returns its HTML.
	 * The companion Part class (its data() injects extra template variables) is
	 * resolved either from the `class.parts` config map (keyed by the exact dot
	 * name, e.g. `post.part.content` => Pomme\View\Part\PommePart::class — for a
	 * part that doesn't live under the current module) or, failing that, by
	 * convention under the current module's namespace: `{CurrentNamespace}\View\Part\{Name}Part`.
	 * @param string $name   dot-notation path e.g. `module.part.scenario`
	 * @param array  $option variables to pass to the template
	 */
	public function part(string $name, array $option = []): string {
		$fileName = $this->makePath($name);

		$segments = explode('.', $name);
		if (($segments[0] ?? '') === 'part') {
			array_shift($segments);
		}
		$partClass = Config::get('class.parts')[$name]
			?? Config::getCurrentNamespace() . '\\View\\Part\\' . implode('', array_map(fn($s) => $this->toCap($s), $segments)) . 'Part';

		if (class_exists($partClass)) {
			$part   = MasterFactory::_make($partClass);
			$option = array_merge($option, $part->data($option));
		}

		return $this->renderFile($fileName, $option);
	}

	/**
	 * Echoes a named property of this view object.
	 * Used in layouts to output buffered sections (e.g. `$this->fetch('content')`).
	 */
	public function fetch(string $var): void {
		echo $this->{$var};
	}

	/**
	 * Starts output buffering for a named block, capturing everything printed
	 * until the matching stopBlock(). Blocks nest (a startBlock() inside another
	 * resumes the outer one once closed), and content accumulates: two
	 * start/stop pairs for the same name append rather than overwrite.
	 */
	public function startBlock(string $name): void {
		$this->blockStack[] = $name;
		ob_start();
	}

	/** Stops buffering the innermost open block and appends the captured output. */
	public function stopBlock(): void {
		$name = array_pop($this->blockStack);
		$this->blocks[$name] = ($this->blocks[$name] ?? '') . ob_get_clean();
	}

	/** Returns the accumulated content of a named block, or '' if it was never started. */
	public function getBlock(string $name): string {
		return $this->blocks[$name] ?? '';
	}

	/**
	 * Appends a `<link>` stylesheet tag to the header buffer.
	 * @param string $path public path to the CSS file
	 */
	public function addCss(string $path): void {
		$this->css .= '<link href="' . $path . '" rel="stylesheet" type="text/css">' . PHP_EOL;
	}

	/**
	 * Appends a `<script>` tag to the header buffer.
	 * @param string $path public path to the JS file
	 * @param string $type MIME type (default `text/javascript`)
	 */
	public function addJs(string $path, string $type = 'text/javascript'): void {
		$this->js .= '<script src="' . $path . '" type="' . $type . '" ></script>' . PHP_EOL;
	}

	/**
	 * Returns the full `<head>` meta/CSS/JS block.
	 * Called inside the layout template: `<?= $this->getHeader() ?>`.
	 */
	public function getHeader(): string {
		$retour  = '<meta name="keywords" content="' . $this->keywords . '" />' . PHP_EOL;
		foreach ($this->meta as $name => $content) {
			$retour .= '<meta name="' . $name . '" content="' . $content . '" />' . PHP_EOL;
		}
		$retour .= $this->css;
		$retour .= $this->js;
		return $retour;
	}

	/** Starts output buffering for an inline `<script>` block. Thin wrapper over startBlock('script'). */
	public function startScript(): void {
		$this->startBlock('script');
	}

	/** Stops buffering and appends the captured output to the script block. */
	public function stopScript(): void {
		$this->stopBlock();
	}

	/** Returns the accumulated inline script content. */
	public function getScript(): string {
		return $this->getBlock('script');
	}

	/** Sets the page title. */
	public function setTitle(string $title): void {
		$this->title = $title;
	}

	/** Returns the page title. */
	public function getTitle(): string {
		return $this->title;
	}

	/** Appends keywords to the meta keywords string. */
	public function addKeyWords(string $words): void {
		$this->keywords .= $words;
	}

	/**
	 * Adds a `<meta name="…">` tag to the header buffer.
	 * @param string $name    meta name attribute
	 * @param string $content meta content attribute
	 */
	public function addMeta(string $name, string $content): void {
		$this->meta[$name] = $content;
	}

	/**
	 * Appends a breadcrumb entry.
	 * @param string       $label display text
	 * @param string|array $url   URL string or route array passed to UrlBuilder
	 * @param string       $icon  optional icon class
	 */
	public function addCrumb(string $label, string|array $url, string $icon = ''): void {
		if (is_array($url)) {
			$url = $this->url($url);
		}
		$this->breadcrumbInfo[] = [
			'label' => $label,
			'url'   => $url,
			'icon'  => $icon,
		];
	}
}
