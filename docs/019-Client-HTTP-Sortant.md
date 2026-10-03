# 19 Le client HTTP sortant

`SkankyDev\Utilities\Http\HttpClient` est un petit wrapper cURL pour les requêtes **sortantes** : appeler une API externe, un service tiers, peu importe. Il n'a pas d'état : chaque requête renvoie son propre `HttpResult`, donc la même instance de `HttpClient` peut servir pour plusieurs appels.

```php
$client = new HttpClient();
$res = $client->timeout(60)->post($url, ['messages' => [...]]); // $data POSTé en JSON (Content-Type auto)

if ($res->ok()) {            // 2xx
    $data = $res->json();    // body décodé (ou ->body() pour le brut)
}
```

## Faire une requête

```php
$client->get($url, ['page' => 2]);      // query string ajoutée automatiquement
$client->post($url, ['champ' => 'v']);  // corps envoyé en JSON
$client->request('PUT', $url, $data);   // n'importe quelle méthode
```

Les réglages se chaînent et se cumulent sur l'instance :

```php
$client->withHeader('Authorization', 'Bearer ' . $token)
       ->withHeaders(['X-Custom' => 'valeur'])
       ->timeout(60); // en secondes, utile pour un appel lent (inférence LLM, etc.)
```

## Le résultat

`HttpResult`, c'est la réponse *reçue* d'un serveur distant. À ne pas confondre avec `SkankyDev\Http\Response`, qui est la réponse *sortante* vers le navigateur ([11](011-Les-Reponses.md)).

```php
$res->status();     // code HTTP (0 si la requête n'a pas abouti)
$res->ok();          // 2xx
$res->failed();      // erreur de transport OU code >= 400
$res->body();        // corps brut
$res->json();        // corps décodé en JSON (null si ce n'est pas du JSON valide)
$res->header('...'); // un header de réponse, insensible à la casse
$res->error();       // message d'erreur de transport (cURL), vide si la requête a abouti
```

Attention : un échec réseau (timeout, DNS, connexion refusée...) **ne lève pas d'exception**. Tu récupères un `HttpResult` avec `status() === 0` et `error()` rempli. À toi de vérifier `ok()` ou `failed()` plutôt que de supposer que la requête est passée.
