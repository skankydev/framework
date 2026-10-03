<?php

namespace SkankyDev\Queue\Job;

use SkankyDev\Utilities\Mail\MailSender;
use SkankyDev\Utilities\Mail\MasterMail;

/**
 * Envoie un MasterMail en tâche de fond via le queue worker.
 * Le mail (sous-classe concrète, ex. NewUserMail) est stocké tel quel comme
 * payload — Mongo le reconstruit avec la bonne classe (cf. MasterMail).
 */
class SendMailJob extends MasterJob {

	public string|array $to;
	public MasterMail $mail;

	public function __construct(string|array $to, MasterMail $mail) {
		$this->to   = $to;
		$this->mail = $mail;
	}

	public function run(): void {
		MailSender::_send($this->to, $this->mail);
	}
}
