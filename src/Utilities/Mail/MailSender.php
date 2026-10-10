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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use SkankyDev\Config\Config;
use SkankyDev\Exception\MailException;
use SkankyDev\Utilities\Traits\Singleton;

/**
 * Envoie des MasterMail par SMTP (PHPMailer), configuré via `smtp` (config/master.config.php).
 * Singleton — utiliser le proxy statique : `MailSender::_send($to, new NewUserMail($user));`
 */
class MailSender {

	use Singleton;

	/**
	 * Envoie un mail à une ou plusieurs adresses.
	 * @param string|string[] $to une adresse, ou un tableau d'adresses (pas de liste séparée par des virgules)
	 * @throws MailException si l'envoi échoue (config SMTP invalide, connexion refusée, ...)
	 */
	public function send(string|array $to, MasterMail $mail): bool {
		$config = Config::get('smtp');
		$mailer = new PHPMailer(true);
		$recipients = is_array($to) ? $to : [$to];

		try {
			$mailer->isSMTP();
			$mailer->Host = $config['host'];
			$mailer->Port = (int) $config['port'];

			if (!empty($config['username'])) {
				$mailer->SMTPAuth = true;
				$mailer->Username = $config['username'];
				$mailer->Password = $config['password'];
			}

			if (in_array($config['secure'], ['tls', 'ssl'], true)) {
				$mailer->SMTPSecure = $config['secure'];
			}

			$mailer->setFrom($config['default_sender']);
			foreach ($recipients as $address) {
				$mailer->addAddress($address);
			}

			foreach ($mail->attachments() as $attachment) {
				$mailer->addAttachment($attachment['path'], $attachment['name']);
			}

			$mailer->isHTML(true);
			$mailer->CharSet = 'UTF-8';
			$mailer->Subject = $mail->subject();
			$mailer->Body    = $mail->content();

			return $mailer->send();
		} catch (PHPMailerException $e) {
			$destinataires = implode(', ', $recipients);
			throw new MailException("Failed to send mail to {$destinataires}: {$e->getMessage()}", 500);
		}
	}
}
