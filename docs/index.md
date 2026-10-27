# omnigame/gog

GOG for [omnisense/omnigame](https://github.com/glitchr-omnisense/omnigame), with no key: a
product's record with its price in a country, and the store's search with prices, studios, genres,
tags and the store's rating. These are the endpoints GOG's own store and GOG Galaxy use; GOG
publishes no API terms for them. The provider calls:

| Operation | Endpoint |
|---|---|
| a record | `GET https://api.gog.com/products/{id}?expand=description&locale=...` |
| its price | `GET https://api.gog.com/products/{id}/prices?countryCode=...` |
| search | `GET https://catalog.gog.com/v1/catalog?query=like:...&limit=...&countryCode=...&locale=...&currencyCode=...` |

No library or ownership: GOG offers no way for a third party to read a player's games.

## Installation

```sh
composer require omnigame/gog
```

Requires PHP 8.2, `omnisense/omnigame` (1.x) and `symfony/http-client` (6.4, 7 or 8).

## Credentials

None.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `locale` | string | `en-US` | no | the texts' locale; a call's language without a region is widened: `en` -> `en-US`, any other `xx` -> `xx-XX` (`fr-FR`, but also `ja-JA`: give `ja-JP` in full) |
| `country` | string | `US` | no | the prices' country |
| `currency` | string | `USD` | no | the search's prices; a record's price is in the country's own currency |
| `user_agent` | string | `omnigame/1.0 (+https://github.com/glitchr-omnisense/omnigame)` | no | |
| `api` | string | `https://api.gog.com` | no | |
| `catalog` | string | `https://catalog.gog.com` | no | |

## Capabilities

| Contract | |
|---|---|
| `SearchInterface` | yes |
| `CatalogueInterface` | by GOG product number (digits only) |
| `LibraryInterface`, `OwnershipInterface` | no |
| prices | yes; keyless |

`attribution`: `Data from GOG.com`.

## Behaviour

**A record.** Two calls: the product, then its prices in the country. A product without a title is
`null`.

| Field | Read from |
|---|---|
| `title`, `identifiers` | `title`, `id` |
| `url` | `links.product_card` |
| `summary` | `description.lead`, else `description.full`, HTML stripped |
| `released` | `release_date` |
| `comingSoon` | `in_development.active` |
| `platforms` | `content_system_compatibility` (`windows`, `osx`, `linux` true) |
| `price` | the first of `_embedded.prices`: `finalPrice` and `basePrice` (`"799 EUR"`), the discount computed |
| `images` | `images.logo2x` (`Logo`), `images.background` (`Background`), `images.icon` (`Icon`), `https:` added to `//` addresses |

Developers, publishers, genres and ratings are not in the product API's answer: the search gives
them.

**Search.** Each product: number, title, `storeLink`, release date (`Y.m.d`), `upcoming` as coming
soon, developers, publishers, genres, tags, operating systems, the price in `currency`
(`finalMoney`, `baseMoney`, the discount computed), `coverVertical` (`Cover`) and
`coverHorizontal` (`Header`), `reviewsRating` divided by 10 as a rating out of 5.

## Example

Run live on 2026-10-27 (06:24 UTC), in the core's harness - two calls:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnigame\Gog\GogFactory;
use Omnigame\Model\GameRef;
use Symfony\Component\HttpClient\HttpClient;

$gog = (new GogFactory(HttpClient::create(['timeout' => 30])))->create();
$g = $gog->game(GameRef::gog(1207658924), 'fr', 'FR');
printf("%s %s, %.2f %s (-%d%%), %s\n", $g->title, $g->released?->format('Y-m-d'), $g->price->amount / 100, $g->price->currency, $g->price->discount, $g->url);
```

```
The Witcher: Enhanced Edition 2011-05-09, 1.19 EUR (-85%), https://www.gog.com/game/the_witcher
```

On the answers recorded on 2026-10-24, the record's other fields (no studio: the product API names
none):

```
The Witcher: Enhanced Edition | 2011-05-09 | 119 EUR initial 799 -85%
  summary The Witcher est un jeu de rôles dans un monde sombre et fantastique où
  logo https://images-2.gog-statics.com/37d4a208d1f5bb0e163da540ac894ba46a7d566ede31aaaefc74bbcd46ebd190_glx_logo_2x.jpg
  background https://images-2.gog-statics.com/c73009d18e964448b9704ad9d69a7c68bab5f8a10df410254bb511f2f9198a3a.jpg
  icon https://images-4.gog-statics.com/82ecfe17e7c3fa7fe3252664927ba9b0014ca17039d8ec1b46275677ab7a7898.png
  > GET https://api.gog.com/products/1207658924 {"expand":"description","locale":"fr-FR"}
  > GET https://api.gog.com/products/1207658924/prices {"countryCode":"FR"}
```

In Symfony:

```yaml
services:
    Omnigame\Gog\GogFactory:
        arguments: ['@http_client']
    Omnigame\Registry:
        arguments:
            $factories: ['@Omnigame\Gog\GogFactory']
            $config:
                gog: { factory: gog, options: { locale: fr-FR, country: FR, currency: EUR } }
```

## Errors

Unknown product: `null`; a ref that is not a GOG number: `NotSupportedException`; a status of 400
or more but 404, or a body that is not JSON: `ProviderException`; a network error:
`UnreachableException`.

## Verified, and not

Tested offline (`Tests/GogProviderTest.php`, in the core's harness, PHP 8.4), on answers recorded
on 2026-10-24: The Witcher's record in French with its French price (1,19 € instead of 7,99 €, 85 %
off that day), an unknown product, a search. `Tests/BareTest.php` loads every class with the
package's requirements alone. Verified live: `Tests/LiveTest.php` on 2026-10-24 (a product with its
US price, a search), and the example above on 2026-10-27.

## Responsibility

An independent, unofficial client, affiliated with GOG in no way. GOG publishes no terms for these
endpoints; its [user agreement](https://support.gog.com/hc/en-us/articles/212632089-GOG-User-Agreement)
governs the store. Keep to a gentle pace, keep what you read, and link to the game's page on GOG:
see the README.
