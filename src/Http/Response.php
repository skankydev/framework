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

use SkankyDev\View\HtmlView;
use SkankyDev\Utilities\Session;

class Response {

	protected array $headers = [];
	protected string $body = '';
	protected int $statusCode = 200;
	protected bool $built = false;
	
	/**
	 * @param string $viewName dot-notation view name e.g. `module.show`
	 * @param array  $data     variables passed to the view
	 */
	public function __construct(
		protected string $viewName = '',
		protected array $data = []
	) {}

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
		echo $this->body;
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
