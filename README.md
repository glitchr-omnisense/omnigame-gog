# omnigame/gog

[omnisense/omnigame](https://github.com/glitchr-omnisense/omnigame)'s GOG, under any PHP host, with no
key: a product's record (`api.gog.com/products/{id}?expand=description`) with its price in the
country asked (`/products/{id}/prices`), and the store's search (`catalog.gog.com/v1/catalog`)
with prices, studios, genres, tags and the store's rating. These are the endpoints GOG's own store
and GOG Galaxy use. No library or ownership: GOG offers no way for a third party to read a player's
games.

```php
$gog = (new GogFactory($httpClient))->create(['locale' => 'fr-FR', 'country' => 'FR', 'currency' => 'EUR']);
$witcher = $gog->game(GameRef::gog(1207658924));
```

Options: `locale` ("en-US"), `country` ("US"), `currency` ("USD": the search's prices; a
record's price is the country's own), `user_agent`, `api`, `catalog`.

## GOG's terms

GOG publishes no terms for these endpoints; its
[user agreement](https://support.gog.com/hc/en-us/articles/212632089-GOG-User-Agreement) governs the
store. Keep to a gentle pace, keep what you read, and link to the game's page on GOG.

## Verified, and not

- Verified (the core's harness, PHP 8.4): The Witcher's record in French with its French price
  (1,19 € instead of 7,99 €, 85 % off on 2026-10-24), an unknown product, a search - answers
  recorded on 2026-10-24; `BareTest`; PHPStan max.
- Verified live (2026-10-24): a product with its US price, a search.

## Responsibility

An independent, unofficial client, affiliated with, endorsed by or sponsored by none of the services
it reaches. The MIT licence covers this code only and grants no right to the service or its data:
whoever runs it complies with the terms above and obtains what they require. The authors publish
it free of charge, make no commercial use of it and accept no liability for its use.

## Licence

MIT.
