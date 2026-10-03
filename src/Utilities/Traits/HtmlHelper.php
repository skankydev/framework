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

namespace SkankyDev\Utilities\Traits;

use SkankyDev\Http\UrlBuilder;

/**
 * View helper methods for generating HTML snippets.
 * Used by HtmlView and any class that needs to produce links or wrapped tags.
 */
trait HtmlHelper {

	/**
	 * Generates an `<a>` tag pointing to a framework URL.
	 * @param  string $content inner HTML/text of the anchor
	 * @param  array  $link    route array passed to UrlBuilder::build()
	 * @param  array  $attr    additional HTML attributes (class, id, …)
	 * @return string          the rendered `<a>` tag
	 */
	public function link(string $content, array $link = [], array $attr = []): string {
		$attr['href'] = UrlBuilder::_build($link);
		return $this->surround($content, 'a', $attr);
	}

	/**
	 * Resolves a route array to a URL string.
	 * @param  array $link route array passed to UrlBuilder::build()
	 * @return string      the URL string
	 */
	public function url(array $link): string {
		return UrlBuilder::_build($link);
	}

	/**
	 * Converts a key-value array to an HTML attribute string.
	 * @param  array  $attr e.g. `['class' => 'btn', 'id' => 'submit']`
	 * @return string       e.g. `class="btn" id="submit" `
	 */
	public function createAttr(array $attr = []): string {
		$retour = '';
		foreach ($attr as $key => $value) {
			$retour .= $key . '="' . $value . '" ';
		}
		return $retour;
	}

	/**
	 * Wraps content in an HTML tag with optional attributes.
	 * @param  string $content inner HTML/text
	 * @param  string $tag     tag name e.g. `div`, `span`
	 * @param  array  $attr    HTML attributes
	 * @return string          the rendered HTML element
	 */
	public function surround(string $content, string $tag, array $attr = []): string {
		return '<' . $tag . ' ' . $this->createAttr($attr) . '>' . $content . '</' . $tag . '>';
	}
}
