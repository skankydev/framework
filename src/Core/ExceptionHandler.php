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

namespace SkankyDev\Core;

use Exception;
use SkankyDev\Config\Config;
use SkankyDev\Http\Request;
use SkankyDev\Utilities\Log;
use Throwable;

class ExceptionHandler {

	/** @var array<string,bool> PHP errors already logged during this request (see handleError()) */
	protected array $seenErrors = [];

	/**
	 * @param bool $debug true renders the full stack trace, false renders a generic error page
	 */
	public function __construct(protected bool $debug = true) { }

	/**
	 * Logs the exception and renders the appropriate error page based on debug mode.
	 */
	public function handle(Throwable $exception): void {
		$this->log($exception);

		if ($this->debug) {
			$this->renderDebug($exception);
		} else {
			$this->renderProduction($exception);
		}
	}

	/**
	 * Logs the exception with its request context, without rendering anything.
	 * Exposé séparément de handle() pour Application::run(), qui gère lui-même
	 * le cas des NotFoundException (redirigées vers la vraie page 404 en prod,
	 * cf. Application.php) — ExceptionHandler reste volontairement ignorant
	 * de ce cas particulier.
	 */
	public function log(Throwable $exception): void {
		Log::error($exception, $this->requestContext());
	}

	/**
	 * PHP error handler (see set_error_handler() in Application): warnings, notices
	 * and deprecations are never silent. Each one is logged (once per request for a
	 * given message/file/line, so a warning in a loop doesn't flood the log) and, in
	 * debug mode, echoed in place as a styled block instead of PHP's raw output.
	 * Not echoed for a JSON request (it would corrupt the body): the log is the trace.
	 * Fatal errors don't come through here, the shutdown function handles them.
	 * @return bool true = handled (PHP's own output is skipped), false = left to PHP
	 */
	public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool {
		// Masked by error_reporting or silenced with @
		if (!(error_reporting() & $severity)) {
			return false;
		}

		$key = $severity . '|' . $message . '|' . $file . '|' . $line;
		if (!isset($this->seenErrors[$key])) {
			$this->seenErrors[$key] = true;
			$this->log(new \ErrorException(self::errorLabel($severity) . ': ' . $message, 0, $severity, $file, $line));
		}

		if ($this->debug && !Request::getInstance()->wantsJson()) {
			echo $this->renderError($severity, $message, $file, $line);
		}

		return true;
	}

	/** Styled block for a PHP error (same `debug-*` CSS classes as debug()). */
	protected function renderError(int $severity, string $message, string $file, int $line): string {
		$esc = fn(string $text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return '<div class="debug-message debug-error">'
			. '<span class="debug-php">' . self::errorLabel($severity) . '</span> : '
			. '<span class="debug-error-text">' . $esc($message) . '</span> in '
			. '<span class="debug-keyword">' . $esc($file) . '</span>:'
			. '<span class="debug-integer">' . $line . '</span>'
			. '</div>';
	}

	/** Human name of a PHP error level. */
	protected static function errorLabel(int $severity): string {
		return match ($severity) {
			E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
			E_NOTICE, E_USER_NOTICE => 'Notice',
			E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
			E_USER_ERROR, E_RECOVERABLE_ERROR => 'Error',
			default => 'PHP error',
		};
	}

	/**
	 * Builds the URL/IP/User-Agent context logged alongside the exception.
	 * Request ne lit que les superglobales ($_SERVER) dans son constructeur —
	 * aucune dépendance à la session/au routing/à la DB, donc sûr à appeler
	 * ici même si l'exception est survenue très tôt dans le cycle de vie.
	 * En CLI (pas de $_SERVER['REQUEST_URI']), retombe sur ses valeurs par
	 * défaut ('/', '0.0.0.0', null) plutôt que de planter.
	 */
	protected function requestContext(): array {
		$request = Request::getInstance();

		return [
			'URL'        => $request->method() . ' ' . $request->fullUri(),
			'IP'         => $request->ip(),
			'User-Agent' => $request->userAgent() ?? '-',
		];
	}

	/**
	 * Renders a detailed error page with class, file, line and full stack trace.
	 * Only shown in debug mode.
	 */
	protected function renderDebug(Throwable $exception): void {
		$this->renderErrorPage('debug.php', $exception);
	}

	/**
	 * Renders a generic error page without exposing any internal details.
	 * Shown in production mode.
	 */
	protected function renderProduction(Throwable $exception): void {
		$this->renderErrorPage('production.php', $exception);
	}

	/**
	 * Renders an error page from Publishable/view/error/{$template} wrapped in
	 * Publishable/view/layout/error.php (paths configurable via `view.error` /
	 * `view.error_layout`, cf. default.config.php). Deliberately independent of
	 * HtmlView/Response (no Request, routing, session…) : this handler must
	 * still work when the rest of the app is broken.
	 */
	protected function renderErrorPage(string $template, Throwable $exception): void {
		http_response_code(500);

		$contentPath = Config::get('view.error') . DS . $template;
		$layoutPath  = Config::get('view.error_layout');

		$content = $this->renderTemplate($contentPath, ['exception' => $exception]);
		echo $this->renderTemplate($layoutPath, ['content' => $content]);
	}

	/**
	 * Includes a plain PHP template with the given variables in scope and
	 * returns its captured output.
	 */
	protected function renderTemplate(string $path, array $data): string {
		extract($data);
		ob_start();
		require $path;
		return ob_get_clean();
	}

}