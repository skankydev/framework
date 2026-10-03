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

use SkankyDev\Model\Document\RateLimitEntry;
use SkankyDev\Model\RateLimitCollection;
use DateTime;

/**
 * Limiteur de tentatives par clé (ex: IP, email) sur une fenêtre glissante fixe.
 * Stocké dans RateLimitCollection ; l'index TTL déclaré sur `expires_at`
 * (cf. RateLimitCollection::getIndexes(), à créer/synchroniser via `php craft db-sync`)
 * fait le ménage tout seul, pas de nettoyage applicatif nécessaire.
 *
 *     if (RateLimiter::tooManyAttempts('login:' . $request->ip(), 5, TIME_MINUTE * 15)) {
 *         return redirect(...)->withFlash('error', 'Trop de tentatives, réessaie plus tard.');
 *     }
 */
class RateLimiter {

	/**
	 * Enregistre une tentative pour $key et retourne true si la limite est dépassée.
	 * La fenêtre repart de zéro dès que $decaySeconds se sont écoulées depuis sa première tentative.
	 * @param string $key           identifiant unique (ex: `login:1.2.3.4`, `password_lost:foo@bar.com`)
	 * @param int    $maxAttempts   nombre de tentatives autorisées sur la fenêtre
	 * @param int    $decaySeconds  durée de la fenêtre en secondes
	 */
	public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool {
		$entry = RateLimitCollection::_findOne(['attempts_key' => $key]) ?? new RateLimitEntry(['attempts_key' => $key]);
		$now = new DateTime();

		if ($entry->expires_at === null || $entry->expires_at < $now) {
			$entry->attempts = 1;
			$entry->expires_at = (clone $now)->modify("+{$decaySeconds} seconds");
		} else {
			$entry->attempts += 1;
		}

		RateLimitCollection::_save($entry);

		return $entry->attempts > $maxAttempts;
	}

	/** Remet le compteur à zéro pour la clé (ex: après un login réussi). */
	public static function clear(string $key): void {
		$entry = RateLimitCollection::_findOne(['attempts_key' => $key]);
		if ($entry !== null) {
			RateLimitCollection::_deleteOne($entry);
		}
	}

}
