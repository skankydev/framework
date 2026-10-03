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
 * Petit client HTTP (cURL) pour les requêtes sortantes.
 *
 * Sans état : chaque requête renvoie un HttpResult autoporteur.
 * Les setters (withHeader, timeout) sont fluent et se cumulent sur l'instance,
 * ce qui permet de réutiliser le même client pour plusieurs appels.
 *
 *     $client = new HttpClient();
 *     $res = $client->post($url, $data);
 *     if ($res->ok()) { $data = $res->json(); }
 */
class HttpClient {

	/** Headers appliqués à chaque requête (clé => valeur). */
	protected array $headers = [];

	/** Timeout en secondes (0 = pas de limite). */
	protected int $timeout = 30;

	/** Ajoute ou remplace un header envoyé avec la requête. */
	public function withHeader(string $name, string $value): self {
		$this->headers[$name] = $value;
		return $this;
	}

	/** Ajoute plusieurs headers d'un coup. */
	public function withHeaders(array $headers): self {
		foreach ($headers as $name => $value) {
			$this->headers[$name] = $value;
		}
		return $this;
	}

	/**
	 * Définit le timeout en secondes (0 = illimité).
	 * Utile pour les appels lents type inférence LLM.
	 */
	public function timeout(int $seconds): self {
		$this->timeout = $seconds;
		return $this;
	}

	/** Requête GET ; $query est sérialisé en query string. */
	public function get(string $url, array $query = []): HttpResult {
		if ($query !== []) {
			$url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
		}
		return $this->request('GET', $url);
	}

	/** Requête POST ; $data est envoyé en JSON. */
	public function post(string $url, array $data = []): HttpResult {
		return $this->request('POST', $url, $data);
	}

	/**
	 * Exécute une requête.
	 *
	 * @param string $method méthode HTTP (GET, POST, ...)
	 * @param string $url    URL absolue
	 * @param array  $data   corps de requête, sérialisé en JSON (ignoré en GET)
	 */
	public function request(string $method, string $url, array $data = []): HttpResult {
		$method = strtoupper($method);
		$headers = $this->headers;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		// Corps JSON pour les méthodes qui en portent un.
		if ($data !== [] && $method !== 'GET') {
			$headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

		// Collecte des headers de réponse (clé minuscule => valeur).
		$responseHeaders = [];
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$responseHeaders) {
			$parts = explode(':', $line, 2);
			if (count($parts) === 2) {
				$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
			}
			return strlen($line);
		});

		$body = curl_exec($ch);
		$error = curl_error($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if ($body === false) {
			return new HttpResult(0, '', [], $error !== '' ? $error : 'Requête HTTP échouée');
		}

		return new HttpResult($status, $body, $responseHeaders, $error);
	}

	/** Transforme la map de headers en lignes "Name: value" pour cURL. */
	protected function formatHeaders(array $headers): array {
		$lines = [];
		foreach ($headers as $name => $value) {
			$lines[] = "{$name}: {$value}";
		}
		return $lines;
	}

}
