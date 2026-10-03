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

namespace SkankyDev\Model\Document;

use SkankyDev\Model\Document\MasterDocument;
use DateTime;

/**
 * Compteur de tentatives pour une clé (cf. SkankyDev\Utilities\RateLimiter).
 * `expires_at` porte l'index TTL (RateLimitCollection::getIndexes()) : Mongo
 * supprime le document tout seul une fois la fenêtre expirée, pas de nettoyage manuel.
 */
class RateLimitEntry extends MasterDocument {

	public string $attempts_key = '';
	public int $attempts = 0;
	public ?DateTime $expires_at = null;

}
