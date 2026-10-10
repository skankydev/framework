# 19 The outgoing HTTP client

`SkankyDev\Utilities\Http\HttpClient` is a small cURL wrapper for **outgoing** requests: calling an external API, a third-party service, whatever. It has no state: each request returns its own `HttpResult`, so the same `HttpClient` instance can be used for several calls.

```php
$client = new HttpClient();
$res = $client->timeout(60)->post($url, ['messages' => [...]]); // $data POSTed as JSON (Content-Type auto)

if ($res->ok()) {            // 2xx
    $data = $res->json();    // decoded body (or ->body() for the raw one)
}
```

## Making a request

```php
$client->get($url, ['page' => 2]);      // query string added automatically
$client->post($url, ['field' => 'v']);  // body sent as JSON
$client->request('PUT', $url, $data);   // any method
```

Settings are chained and add up on the instance:

```php
$client->withHeader('Authorization', 'Bearer ' . $token)
       ->withHeaders(['X-Custom' => 'value'])
       ->timeout(60); // in seconds, useful for a slow call (LLM inference, etc.)
```

## The result

`HttpResult` is the response *received* from a remote server. Not to be confused with `SkankyDev\Http\Response`, which is the *outgoing* response to the browser ([11](011-Responses.md)).

```php
$res->status();     // HTTP code (0 if the request didn't go through)
$res->ok();          // 2xx
$res->failed();      // transport error OR code >= 400
$res->body();        // raw body
$res->json();        // body decoded as JSON (null if it isn't valid JSON)
$res->header('...'); // a response header, case-insensitive
$res->error();       // transport error message (cURL), empty if the request went through
```

Careful: a network failure (timeout, DNS, connection refused...) **doesn't throw an exception**. You get an `HttpResult` with `status() === 0` and `error()` filled in. It's up to you to check `ok()` or `failed()` rather than assuming the request went through.
