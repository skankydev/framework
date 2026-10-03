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

use SkankyDev\Utilities\Traits\Singleton;

class Request {

	use Singleton;

	protected array $query;      // $_GET
	protected array $post;       // $_POST
	protected array $files;      // $_FILES (ton joli tableau)
	protected array $cookies;    // $_COOKIE
	protected array $server;     // $_SERVER
	protected array $headers;    // Headers HTTP
	protected ?string $body;     // Body brut
	protected ?array $json;      // Body parsé si JSON

	/**
	 * Populates all request data from PHP superglobals.
	 * Files are normalized so multiple-file fields have a consistent structure.
	 */
	public function __construct(){
		$this->query = $_GET;
		$this->post = $_POST;
		$this->files = $this->normalizeFiles();
		$this->cookies = $_COOKIE;
		$this->server = $_SERVER;
		$this->headers = $this->parseHeaders();
		$this->body = file_get_contents('php://input');
		$this->json = $this->parseJson();
	}

	/** Returns the HTTP method (GET, POST, PUT, etc.). */
	public function method(): string {
		return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
	}

	/** Returns the request scheme (http or https). */
	public function scheme(): string {
		return $this->server['REQUEST_SCHEME'] ?? 'http';
	}

	/** Returns the HTTP host (e.g. `skankyblog.local`). */
	public function host(): string {
		return $this->server['HTTP_HOST'] ?? '';
	}

	

	/** Returns the URI path without the query string (e.g. `/module/show/abc`). */
	public function uri(): string {
		return strtok($this->server['REQUEST_URI'] ?? '/', '?');
	}

	/** Returns the full URI including query string. */
	public function fullUri(): string {
		return $this->server['REQUEST_URI'] ?? '/';
	}

	/** Returns a GET parameter by key, all GET params if key is null, or $default if not found. */
	public function query(?string $key = null, mixed $default = null): mixed {
		if ($key === null) {
			return $this->query;
		}
		return $this->query[$key] ?? $default;
	}

	/** Returns a POST parameter by key, all POST params if key is null, or $default if not found. */
	public function post(?string $key = null, mixed $default = null): mixed {
		if ($key === null) {
			return $this->post;
		}
		return $this->post[$key] ?? $default;
	}

	/**
	 * Returns merged input from POST, JSON body and GET, in that priority order.
	 * Returns all merged input if key is null.
	 */
	public function input(?string $key = null, mixed $default = null): mixed {
		if ($key === null) {
			return array_merge($this->query, $this->post, $this->json ?? []);
		}

		// Priorité : POST > JSON > GET
		return $this->post[$key] 
			?? ($this->json[$key] ?? null)
			?? $this->query[$key] 
			?? $default;
	}

	/** Returns a normalized uploaded file array by key, or all files if key is null. */
	public function file(?string $key = null): mixed {
		if ($key === null) {
			return $this->files;
		}
		return $this->files[$key] ?? null;
	}

	/** Returns a cookie value by key, all cookies if key is null, or $default if not found. */
	public function cookie(?string $key = null, mixed $default = null): mixed {
		if ($key === null) {
			return $this->cookies;
		}
		return $this->cookies[$key] ?? $default;
	}

	/** Returns a request header by lowercase key, all headers if key is null, or $default if not found. */
	public function header(?string $key = null, mixed $default = null): mixed {
		if ($key === null) {
			return $this->headers;
		}
		$key = strtolower($key);
		return $this->headers[$key] ?? $default;
	}

	/** Returns the raw request body. */
	public function body(): ?string {
		return $this->body;
	}

	/** Returns the parsed JSON body by key, all decoded JSON if key is null, or $default if not found. */
	public function json(?string $key = null, mixed $default = null): mixed {
		if ($this->json === null) {
			return $default;
		}
		if ($key === null) {
			return $this->json;
		}
		return $this->json[$key] ?? $default;
	}

	/** Returns the client IP, respecting X-Forwarded-For for proxied requests. */
	public function ip(): string {
		// Gérer les proxies
		if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
			return trim($ips[0]);
		}
		return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/** Returns the User-Agent string, or null if not present. */
	public function userAgent(): ?string {
		return $this->server['HTTP_USER_AGENT'] ?? null;
	}

	/** Returns true if the request was made via XMLHttpRequest (X-Requested-With header). */
	public function isAjax(): bool {
		return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
	}

	/** Returns true if the Content-Type header indicates a JSON body. */
	public function isJson(): bool {
		return str_contains(
			strtolower($this->header('content-type', '')), 
			'application/json'
		);
	}

	/** Returns true if the client accepts a JSON response (Accept header). */
	public function wantsJson(): bool {
		$accept = strtolower($this->header('accept', ''));
		return str_contains($accept, 'application/json');
	}

	/**
	 * Extracts HTTP headers from $_SERVER into a lowercase-keyed array.
	 * Also captures CONTENT_TYPE and CONTENT_LENGTH which are not prefixed with HTTP_.
	 */
	protected function parseHeaders(): array {
		$headers = [];
		foreach ($this->server as $key => $value) {
			if (str_starts_with($key, 'HTTP_')) {
				$header = str_replace('_', '-', substr($key, 5));
				$headers[strtolower($header)] = $value;
			}
			// Headers spéciaux
			if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
				$header = str_replace('_', '-', $key);
				$headers[strtolower($header)] = $value;
			}
		}
		return $headers;
	}

	/** Decodes the raw body as JSON if Content-Type is application/json. Returns null on failure. */
	protected function parseJson(): ?array {
		if ($this->isJson() && !empty($this->body)) {
			$decoded = json_decode($this->body, true);
			return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
		}
		return null;
	}

	/**
	 * Normalizes $_FILES so that multi-file fields are indexed as array of files
	 * rather than PHP's default structure of arrays of properties.
	 */
	protected function normalizeFiles(): array {

		$normalized = [];
		foreach ($_FILES as $field => $files) {
			$test = current($files);
			if(is_array($test)){
				foreach ($files as $index => $value) {
					foreach ($value as $key => $v) {
						$normalized[$field][$key][$index] = $v;
					}
				}
			}else{
				$normalized[$field] = $files;
			}
		}

		return $normalized;
	}

	/** Magic property access — proxies to input() so `$request->name` works. */
	public function __get(string $name): mixed {
		return $this->input($name);
	}

	/**
	 * Returns pagination info extracted from the query string: current page and sort order.
	 * @param  array $defaultSort fallback sort if no `field`/`order` params are present
	 * @return array{page: int, sort: array}
	 */
	public function paginateInfo(array $defaultSort = []): array {
		return [
			'page' => (int)($this->query('page') ?? 1),
			'sort' => $this->getSort($defaultSort),
		];
	}

	/**
	 * Builds the sort array from `field` and `order` query params.
	 * Returns $default if either param is missing.
	 */
	protected function getSort(array $default = []): array {
		$field = $this->query('field');
		$order = $this->query('order');

		if ($field && $order) {
			return [$field => (int) $order];
		}

		return $default;
	}
}
