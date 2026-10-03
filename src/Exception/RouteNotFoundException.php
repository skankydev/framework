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

namespace SkankyDev\Exception;

/** Levée quand UrlBuilder::build() reçoit un lien avec une clé 'name' qui ne correspond à aucune route déclarée. */
class RouteNotFoundException extends NotFoundException {

}
