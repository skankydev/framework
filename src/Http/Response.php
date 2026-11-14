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

namespace SkankyDev\Http;

use SkankyDev\Exception\NotFoundException;
use SkankyDev\Model\Document\StoredFile;
use SkankyDev\View\HtmlView;
use SkankyDev\Utilities\Session;

class Response {

	/**
	 * Types the browser would run as a page (scripts included) if shown inline:
	 * Response::file() always sends them as a download.
	 */
	private const NEVER_INLINE = [
		'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
		'text/javascript', 'application/javascript',
	];

	protected array $headers = [];
	protected string $body = '';
	protected int $statusCode = 200;
	protected bool $built = false;
	/** File sent as the body by send() (cf. Response::file()), streamed instead of $body. */
	protected ?string $filePath = null;

	/**
	 * @param string $viewName dot-notation view name e.g. `module.show`
	 * @param array  $data     variables passed to the view
	 */
	public function __construct(
		protected string $viewName = '',
		protected array $data = []
	) {}

	/**
	 * Response sending a file from the disk: typically a private StoredFile
	 * (UploadedFile::storePrivate()), once the controller has checked who asks for it.
	 * @param StoredFile|string $file     a StoredFile, or the absolute path of a file
	 * @param string|null       $name     name proposed to the browser (default: the original
	 *                                    name of the StoredFile, or the file's basename)
	 * @param bool              $download true to force the download, false to display it in
	 *                                    the browser when it can (image, PDF...)
	 * @throws NotFoundException (code 404) when the file doesn't exist
	 */
	public static function file(StoredFile|string $file, ?string $name = null, bool $download = false): static {
		$stored = $file instanceof StoredFile ? $file : null;
		$path   = $stored ? $stored->fullPath() : $file;
		if (!is_file($path)) {
			throw new NotFoundException("File not found: {$path}", 404);
		}

		$mime = $stored?->mime ?: (mime_content_type($path) ?: 'application/octet-stream');
		$name ??= ($stored?->original_name ?: basename($path));
		$disposition = $download || in_array($mime, self::NEVER_INLINE, true) ? 'attachment' : 'inline';
		// filename : repli ASCII pour les vieux navigateurs, filename* : le vrai nom en UTF-8 (RFC 6266)
		$ascii = str_replace(['"', '\\'], '', preg_replace('/[^\x20-\x7E]/', '_', $name));

		$response = new static();
		$response->filePath = $path;
		$response->built = true;
		return $response
			->header('Content-Type', $mime)
			->header('Content-Length', (string) filesize($path))
			->header('Content-Disposition', "{$disposition}; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name))
			->header('X-Content-Type-Options', 'nosniff')
			->header('Cache-Control', 'private, no-cache');
	}

	/** Adds or overrides a response header. */
	public function header(string $name, string $value): self {
		$this->headers[$name] = $value;
		return $this;
	}

	/** Sets the HTTP status code. */
	public function status(int $code): self {
		$this->statusCode = $code;
		return $this;
	}
	
	/** Sets the view name to render. */
	public function viewName(string $viewName): self {
		$this->viewName = $viewName;
		return $this;
	}

	/**
	 * Renders the response body.
	 * Outputs JSON when the client asks for it (Accept) OR when there is no view
	 * to render (a data response built via response()) ; otherwise renders the HTML view.
	 */
	public function build(): self {
		if(Request::_wantsJson() || $this->viewName === ''){
			$this->header('content-type', 'application/json');
			$this->body = json_encode($this->data);
		}else{
			$view = new HtmlView($this->viewName,$this->data);
			$this->header('Content-Type', 'text/html; charset=UTF-8');
			$this->body = $view->render();
		}
		$this->built = true;
		return $this;
	}

	/**
	 * Sends the response to the client: status code, headers and body.
	 * Calls build() automatically for 2xx responses if not already built.
	 * Falls back to a JS redirect if headers are already sent and status is 3xx.
	 */
	public function send(): void {
		// Build le body pour les réponses 2xx et les erreurs (4xx/5xx) ;
		// pas pour les redirections 3xx qui n'ont pas de corps.
		if (!$this->built && ($this->statusCode < 300 || $this->statusCode >= 400)) {
			$this->build();
		}
		
		if (!headers_sent()) {
			http_response_code($this->statusCode);
			
			foreach ($this->headers as $name => $value) {
				header("{$name}: {$value}");
			}
			
			// Si c'est une redirection, exit immédiatement
			if ($this->statusCode >= 300 && $this->statusCode < 400) {
				exit;
			}
		} else {
			// Headers déjà envoyés ET c'est une redirection = gros problème
			if ($this->statusCode >= 300 && $this->statusCode < 400) {
				// Fallback JS pour la redirection
				$location = $this->headers['Location'] ?? '/';
				echo "<script>window.location.href = '{$location}';</script>";
			}
		}
		if ($this->filePath !== null) {
			readfile($this->filePath);
			return;
		}
		echo $this->body;
	}

	/** Headers set so far (name => value). */
	public function getHeaders(): array {
		return $this->headers;
	}

	/** Flashes validation errors to the session for the next request. */
	public function withErrors(array $errors): self {
		Session::set('errors', $errors);
		return $this;
	}

	/** Flashes old input data to the session so forms can be re-populated on validation failure. */
	public function withInput(array $input): self {
		Session::set('old', $input);
		return $this;
	}

	/**
	 * Adds a flash message to the session (e.g. `success`, `error`).
	 * @param string $type    flash type key (success, error, warning, info)
	 * @param string $message message to display on the next request
	 */
	public function withFlash(string $type, string $message): self {
		flash($type,$message);
		return $this;
	}

}
