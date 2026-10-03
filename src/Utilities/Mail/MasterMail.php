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

namespace SkankyDev\Utilities\Mail;

use SkankyDev\Model\Document\EmbeddedDocument;
use SkankyDev\View\HtmlView;

/**
 * Base d'un mail applicatif : porte le sujet et les données du contenu.
 * Le contenu est rendu via une vue (dot-notation, ex. `mail.new-user`), sans layout web.
 *
 * Extends EmbeddedDocument (Persistable) pour pouvoir être stocké tel quel comme
 * payload d'un SendMailJob (cf. SkankyDev\Queue\Job\SendMailJob) — Mongo le
 * reconstruit avec la bonne sous-classe (NewUserMail, ...) via `__pclass`.
 * Ne mettre dans `$data` que des valeurs scalaires (pas un Document complet type
 * User) : tout ce qui va dans le job traîne dans la collection `jobs` jusqu'à
 * son traitement.
 *
 *     class NewUserMail extends MasterMail {
 *         protected string $view = 'mail.new-user';
 *         public function __construct(string $name, string $verifyUrl) {
 *             $this->data = ['name' => $name, 'verifyUrl' => $verifyUrl];
 *             $this->addAttachment('/path/to/cgu.pdf', 'CGU.pdf');
 *         }
 *         public function subject(): string { return 'Bienvenue !'; }
 *     }
 */
abstract class MasterMail extends EmbeddedDocument {

	protected string $view = '';
	protected array  $data = [];

	/** @var array<array{path: string, name: string}> */
	protected array $attachments = [];

	/** Sujet du mail. */
	abstract public function subject(): string;

	/** Rend la vue du mail (sans layout web) et retourne le HTML du corps. */
	public function content(): string {
		$view = new HtmlView($this->view, $this->data);
		$view->setLayout(null);
		return $view->render();
	}

	/**
	 * Ajoute une pièce jointe depuis un fichier sur le disque.
	 * @param string $path chemin absolu du fichier
	 * @param string $name nom affiché au destinataire (défaut : nom du fichier)
	 */
	public function addAttachment(string $path, string $name = ''): self {
		$this->attachments[] = ['path' => $path, 'name' => $name !== '' ? $name : basename($path)];
		return $this;
	}

	/** @return array<array{path: string, name: string}> */
	public function attachments(): array {
		return $this->attachments;
	}
}
