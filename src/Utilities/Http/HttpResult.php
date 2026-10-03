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

namespace SkankyDev\Utilities\Http;

/**
 * Résultat d'une requête HTTP sortante (cf. HttpClient).
 *
 * Objet sans état, autoporteur : on le retourne et on le balade.
 * À ne pas confondre avec SkankyDev\Http\Response (la réponse *sortante*
 * vers le navigateur) — ici c'est la réponse *reçue* d'un serveur distant.
 */
class HttpResult {

	/**
	 * @param int    $statusCode code HTTP de la réponse (0 si la requête a échoué avant d'aboutir)
	 * @param string $body       corps brut de la réponse
	 * @param array  $headers    headers de réponse (clé minuscule => valeur)
	 * @param string $error      message d'erreur transport (cURL) si la requête n'a pas abouti
	 */
	public function __construct(
		protected int $statusCode = 0,
		protected string $body = '',
		protected array $headers = [],
		protected string $error = ''
	) {}

	/** Code HTTP de la réponse. */
	public function status(): int {
		return $this->statusCode;
	}

	/** Vrai si le code est un 2xx. */
	public function ok(): bool {
		return $this->statusCode >= 200 && $this->statusCode < 300;
	}

	/** Vrai si la requête a échoué (erreur transport ou code >= 400). */
	public function failed(): bool {
		return $this->error !== '' || $this->statusCode >= 400;
	}

	/** Corps brut de la réponse. */
	public function body(): string {
		return $this->body;
	}

	/**
	 * Corps décodé depuis le JSON.
	 * @param bool $assoc tableau associatif (true) ou objet (false)
	 * @return mixed null si le body n'est pas du JSON valide
	 */
	public function json(bool $assoc = true): mixed {
		return json_decode($this->body, $assoc);
	}

	/**
	 * Un header de réponse (insensible à la casse), ou null s'il est absent.
	 */
	public function header(string $name): ?string {
		return $this->headers[strtolower($name)] ?? null;
	}

	/** Tous les headers de réponse. */
	public function headers(): array {
		return $this->headers;
	}

	/** Message d'erreur transport (vide si la requête a abouti). */
	public function error(): string {
		return $this->error;
	}

}
