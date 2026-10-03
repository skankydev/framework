# 16 Craft et CLI (CrudMaker + Publishable)

## Le point d'entrée

Tout passe par `php craft <signature> [options]`. C'est un seul fichier à la racine du projet, qui démarre `CliApplication` avec `$argv` :

```php
require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/config/bootstrap.php');

use SkankyDev\Cli\CliApplication;
$app = new CliApplication($argv);
```

`CliApplication` initialise la config (comme côté web), lit les arguments, puis retrouve et exécute la commande demandée. `php craft` tout seul (ou `help`) affiche la liste des commandes disponibles avec leur description.

### Les arguments

Une convention simple, sans dépendance à un parseur externe :

- le premier argument sans `-` est la commande (`php craft queue-worker` → `queue-worker`) ;
- `--cle=valeur` ou `--cle valeur` donne `['cle' => 'valeur']` ;
- `--flag` tout seul donne `['flag' => true]` ;
- `-h` est un alias de `help` ;
- une valeur sans clé est indexée numériquement.

## Créer une commande

Tu crées une classe dans le dossier `Command/` de ton module, qui étend `MasterCommand`, avec une signature et une aide :

```php
class Cleanup extends MasterCommand {
    static protected string $signature = 'cleanup';
    static protected string $help = 'Nettoie les fichiers temporaires';

    public function run(array $arg = []): void {
        $this->info('Nettoyage en cours...');
        // ...
        $this->success('Terminé !');
    }
}
```

`$arg` contient les options parsées, sans la clé `command` (déjà consommée par `CliApplication`).

Tu n'as rien à enregistrer : `CliApplication::autoRegister()` scanne le dossier `Command/` de chaque module déclaré en config, plus celui de SkankyDev lui-même. Une classe valide dans le bon dossier suffit pour apparaître dans `php craft help`.

### Les helpers d'affichage

`MasterCommand` te donne de quoi parler à l'utilisateur (c'est le trait `CliMessage`) :

```php
$this->info('...');     // cyan
$this->success('...');  // vert
$this->warning('...');  // jaune
$this->error('...');    // rouge
$this->text('...');     // brut

$this->ask('Ton nom');           // pose une question, retourne la saisie
$this->valide('Confirmer');      // confirmation (y/n)
$this->choice(['a' => 'Option A', 'b' => 'Option B']); // menu numéroté
```

## Les commandes livrées

- **`crud-maker`** : génère, de manière interactive, un Document, sa Collection, son Controller, un Form et les 4 vues CRUD (index, create, edit, show) à partir de templates (voir le Publishable plus bas).
- **`db-sync`** : synchronise les index Mongo déclarés (`getIndexes()` de chaque Collection, voir [09 Model](009-Model.md)).
- **`queue-worker`** : lance le worker qui traite les jobs en attente (voir [17 La Queue et les Jobs](017-Queue-et-Jobs.md)).
- **`publish`** : copie dans ton projet les ressources par défaut du framework (détaillé juste en dessous).

## Le pattern Publishable

Le dossier `Publishable/` du framework (`vendor/skankydev/framework/src/Publishable/`) contient les ressources génériques du framework qui ont une valeur par défaut, mais qu'un projet peut vouloir personnaliser : les vues d'erreur ([14](014-Gestion-des-Erreurs.md)), les templates du CrudMaker, quelques parts utilitaires (`table`, `paginator`, voir [10 View](010-View.md)), les templates des fields du `FormBuilder` ([12.1](012.1-Les-Fields.md)).

Tant que tu n'y touches pas, presque tout marche avec les défauts du framework : les clés de config concernées (`view.error`, `template.folder`, `view.fields`...) pointent directement dans `Publishable/`. **Les parts (`table`, `paginator`) sont l'exception** : elles n'ont pas de clé de config, `part()` ne regarde que ton dossier de vues ([10 View](010-View.md)), donc il faut les publier pour qu'elles marchent (le starter les contient déjà). Le jour où tu veux personnaliser une de ces pièces, ou pour récupérer les parts, `php craft publish` la copie dans ton propre projet :

```bash
php craft publish -p=error      # une ressource précise
php craft publish               # menu interactif
```

La commande affiche ensuite le morceau de config à vérifier ou à ajouter dans `config/master.config.php` pour pointer sur ta copie. Elle **ne modifie jamais ce fichier toute seule**, je trouvais trop risqué de casser sa structure à coups d'édition automatique. Et si un fichier de destination existe déjà, elle te demande confirmation avant de l'écraser.

Le but : éviter que des fichiers génériques, jamais personnalisés, traînent dans chaque nouveau projet. Publier est un choix explicite, pas un point de départ.
