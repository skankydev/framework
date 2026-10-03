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

use Exception;

/**
 * Marqueur commun pour les exceptions "je ne trouve pas X" (méthode,
 * controller/param, donnée…). En production, l'ExceptionHandler affiche la
 * vraie page 404 pour toute exception de cette famille plutôt que la page
 * d'erreur générique — en debug, la trace complète reste affichée comme
 * pour n'importe quelle autre exception.
 */
class NotFoundException extends Exception {

}
