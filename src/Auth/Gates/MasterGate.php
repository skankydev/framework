<?php

namespace SkankyDev\Auth\Gates;

/**
 * Contrat commun aux gates (mécanismes d'authentification résolus par
 * Auth::keeper() depuis la config `auth.keepers.*` + `class.gates`).
 * Une gate ne connaît que son provider (classe Document, ex: App\Model\Document\User)
 * et sa config — jamais un modèle concret en dur.
 */
abstract class MasterGate {

	private ?object $resolvedUser = null;
	private bool $resolved = false;

	public function __construct(
		protected array $config,
		protected string $providerClass
	) {}

	/** Vérifie des credentials et connecte l'utilisateur si valides. */
	public function attempt(array $credentials): bool {
		return false;
	}

	/** Connecte explicitement un utilisateur déjà résolu (ex: après inscription). */
	public function login(object $user): void {
		$this->reset();
	}

	/** Déconnecte l'utilisateur courant. */
	public function logout(): void {
		$this->reset();
	}

	/** Utilisateur authentifié pour la requête courante, résolu une seule fois. */
	public function user(): ?object {
		if (!$this->resolved) {
			$this->resolvedUser = $this->resolve();
			$this->resolved = true;
		}
		return $this->resolvedUser;
	}

	public function check(): bool {
		return $this->user() !== null;
	}

	public function id(): mixed {
		return $this->user()?->_id;
	}

	/** Résolution effective, propre à chaque gate (session, token...). */
	abstract protected function resolve(): ?object;

	/** Instance de la Collection du provider configuré (ex: UserCollection). */
	protected function provider(): object {
		$collectionClass = ($this->providerClass)::collectionName();
		return $collectionClass::getInstance();
	}

	/** Oblige un nouveau resolve() au prochain appel à user() (après login/logout). */
	protected function reset(): void {
		$this->resolvedUser = null;
		$this->resolved = false;
	}

}
