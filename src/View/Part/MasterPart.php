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

namespace SkankyDev\View\Part;

/**
 * Base class for view parts (similar to Laravel View Composers / CakePHP View Cells).
 * Concrete Part classes live in App\View\Part\ and are auto-discovered by HtmlView::part().
 * data() receives the options passed at the call site and returns extra variables
 * to be merged before the template is rendered.
 */
abstract class MasterPart {

	/**
	 * Returns additional template variables for the part.
	 * @param  array $options variables already passed to part()
	 * @return array          extra variables to merge into the template scope
	 */
	abstract public function data(array $options): array;
}
