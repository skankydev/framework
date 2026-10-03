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
 * Hachage des mots de passe (Argon2id si dispo, sinon bcrypt) et des tokens
 * à usage unique (reset password, remember-me...) avant stockage en base.
 *
 * Un mot de passe se vérifie avec check() : le sel et les paramètres de coût
 * sont embarqués dans le hash, pas besoin de clé applicative pour ça.
 * Un token (déjà aléatoire, cf. Token) se hashe avec hashToken() : on ne
 * stocke jamais sa valeur en clair, seulement son empreinte, pour comparer
 * sans exposer le secret si la base fuite.
 */
class Hash {

	/** Argon2id si le build PHP le supporte, sinon bcrypt. */
	private static function algo(): string {
		return in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
	}

	/** Hash un mot de passe pour stockage. */
	public static function make(string $value): string {
		return password_hash($value, self::algo());
	}

	/** Vérifie un mot de passe en clair contre un hash stocké. */
	public static function check(string $value, string $hashed): bool {
		return password_verify($value, $hashed);
	}

	/** true si le hash a été fait avec un algo/coût différent de l'actuel (à re-hasher après login). */
	public static function needsRehash(string $hashed): bool {
		return password_needs_rehash($hashed, self::algo());
	}

	/** Empreinte SHA-256 d'un token, à stocker à la place de sa valeur en clair. */
	public static function hashToken(string $token): string {
		return hash('sha256', $token);
	}

}
