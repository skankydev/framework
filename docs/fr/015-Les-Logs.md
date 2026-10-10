# 15 Les Logs

Pour logger, il y a `SkankyDev\Utilities\Log`, une classe statique. Elle écrit un fichier par jour dans `logs/` à la racine du projet, créé automatiquement au besoin.

```php
Log::info('message', 'context');      // logs/{date}-context.log (context par défaut : 'skankydev')
Log::warning('message', 'context');   // même fichier que info, préfixe WARNING
Log::debug('message', $context);      // logs/{date}-debug.log, ne fait rien si debug est désactivé
Log::error($exception, $context);     // logs/{date}-error.log, exception complète + trace
Log::job($jobName, $status, $details);// logs/{date}-jobs.log, cycle de vie d'un job
```

Ce n'est pas réservé aux exceptions. `Queue` et `QueueWork` s'en servent pour tracer la vie des jobs (`queued`, `completed`, `failed`, voir [17 La Queue et les Jobs](017-Queue-et-Jobs.md)), indépendamment de toute erreur.

`Log::error()` est ce qu'appelle `ExceptionHandler` ([14 Gestion des erreurs](014-Gestion-des-Erreurs.md)), mais rien ne t'empêche de l'appeler toi-même ailleurs.

`Log::debug()` est le seul à être conditionnel : si la config `debug` est désactivée, il ne fait rien, sans bruit. Pas la peine de l'entourer d'un `if` à chaque appel.

## Nettoyage

`Log::cleanup(int $days = 10)` supprime les fichiers de `logs/` plus vieux que `$days` jours. Rien ne l'appelle automatiquement dans le framework : c'est à toi de le déclencher (une commande CLI dédiée, un cron côté projet) si tu veux une rotation.
