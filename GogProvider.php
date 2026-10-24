<?php

namespace Omnigame\Gog;

use Omnigame\Capabilities;
use Omnigame\CatalogueInterface;
use Omnigame\Exception\NotSupportedException;
use Omnigame\Http\Data;
use Omnigame\Http\JsonClient;
use Omnigame\Model\Game;
use Omnigame\Model\GameRef;
use Omnigame\Model\Identifiers;
use Omnigame\Model\Image;
use Omnigame\Model\ImageKind;
use Omnigame\Model\Platform;
use Omnigame\Model\Price;
use Omnigame\Model\Query;
use Omnigame\Model\Rating;
use Omnigame\Model\Scheme;
use Omnigame\ProviderInterface;
use Omnigame\SearchInterface;

/**
 * GOG's catalogue, with no key: a product's record (api.gog.com/products,
 * with its description, and its price in a country from /prices) and the
 * store's search (catalog.gog.com). These are the endpoints GOG's own store
 * and GOG Galaxy use; GOG publishes no API terms for them. No ownership: GOG
 * offers no way for a third party to read a player's library.
 */
final class GogProvider implements ProviderInterface, SearchInterface, CatalogueInterface
{
    public function __construct(
        private readonly JsonClient $json,
        private readonly string $locale = 'en-US',
        private readonly string $country = 'US',
        private readonly string $currency = 'USD',
        private readonly string $api = 'https://api.gog.com',
        private readonly string $catalog = 'https://catalog.gog.com',
    ) {
    }

    public function getName(): string
    {
        return 'gog';
    }

    public function getTitle(): string
    {
        return 'GOG';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            search: true,
            catalogue: true,
            lookupBy: [Scheme::Gog],
            prices: true,
            attribution: 'Data from GOG.com',
            terms: ['https://support.gog.com/hc/en-us/articles/212632089-GOG-User-Agreement'],
            keyless: true,
        );
    }

    public function search(Query $query): array
    {
        $answer = $this->json->get($this->catalog.'/v1/catalog', ['query' => 'like:'.$query->text, 'limit' => $query->limit, 'countryCode' => strtoupper($query->country ?? $this->country), 'locale' => $this->locale($query->language), 'currencyCode' => $this->currency]);
        $games = [];
        foreach ($answer?->list('products') ?? [] as $product) {
            $images = [];
            foreach (['coverVertical' => ImageKind::Cover, 'coverHorizontal' => ImageKind::Header] as $field => $kind) {
                if (null !== $url = $product->string($field)) {
                    $images[] = new Image($kind, $url);
                }
            }
            $rating = $product->int('reviewsRating');
            $final = $product->float('price.finalMoney.amount');
            $base = $product->float('price.baseMoney.amount');
            $currency = $product->string('price.finalMoney.currency');
            $games[] = new Game(
                title: (string) $product->string('title'),
                identifiers: Identifiers::of(['gog' => $product->string('id')]),
                source: 'gog',
                url: $product->string('storeLink'),
                released: $product->date('releaseDate', 'Y.m.d'),
                comingSoon: 'upcoming' === $product->string('productState'),
                developers: $product->strings('developers'),
                publishers: $product->strings('publishers'),
                genres: $product->strings('genres', 'name'),
                tags: $product->strings('tags', 'name'),
                platforms: Platform::fromLabels($product->strings('operatingSystems')),
                price: null === $final || null === $currency ? null : self::price((int) round($final * 100), null === $base ? null : (int) round($base * 100), $currency),
                images: $images,
                ratings: null !== $rating && $rating > 0 ? [new Rating('gog', $rating / 10, 5.0)] : [],
            );
        }

        return $games;
    }

    public function game(GameRef $ref, ?string $language = null, ?string $country = null): ?Game
    {
        if (!$this->capabilities()->resolves($ref) || !ctype_digit($ref->id)) {
            throw NotSupportedException::ref($this->getTitle(), $ref);
        }
        $product = $this->json->get($this->api.'/products/'.$ref->id, ['expand' => 'description', 'locale' => $this->locale($language)]);
        if (null === $product || null === $product->string('title')) {
            return null;
        }
        $images = [];
        foreach (['logo2x' => ImageKind::Logo, 'background' => ImageKind::Background, 'icon' => ImageKind::Icon] as $field => $kind) {
            if (null !== $url = $product->string('images.'.$field)) {
                $images[] = new Image($kind, str_starts_with($url, '//') ? 'https:'.$url : $url);
            }
        }
        $description = $product->string('description.lead') ?? $product->string('description.full');
        $prices = $this->json->get($this->api.'/products/'.$ref->id.'/prices', ['countryCode' => strtoupper($country ?? $this->country)]);
        $first = $prices?->list('_embedded.prices')[0] ?? null;

        return new Game(
            title: (string) $product->string('title'),
            identifiers: Identifiers::of(['gog' => $product->string('id') ?? $ref->id]),
            source: 'gog',
            url: $product->string('links.product_card'),
            summary: null === $description ? null : trim(html_entity_decode(strip_tags($description), \ENT_QUOTES | \ENT_HTML5)),
            released: $product->date('release_date'),
            comingSoon: true === $product->bool('in_development.active'),
            platforms: self::platforms($product->data('content_system_compatibility')),
            price: null === $first ? null : self::fromPrices($first),
            images: $images,
        );
    }

    /** "799 EUR" and "119 EUR": minor units and the currency. */
    private static function fromPrices(Data $price): ?Price
    {
        $final = $price->string('finalPrice');
        $currency = $price->string('currency.code');
        if (null === $final || null === $currency || !preg_match('~^(\d+)~', $final, $f)) {
            return null;
        }
        $base = preg_match('~^(\d+)~', (string) $price->string('basePrice'), $b) ? (int) $b[1] : null;

        return self::price((int) $f[1], $base, $currency);
    }

    private static function price(int $amount, ?int $base, string $currency): Price
    {
        $discounted = null !== $base && $base > $amount;

        return new Price($amount, $currency, $discounted ? $base : null, $discounted ? (int) round(100 * ($base - $amount) / $base) : 0);
    }

    /** @return list<Platform> */
    private static function platforms(Data $compatibility): array
    {
        return Platform::fromLabels(array_map('strval', array_keys(array_filter($compatibility->toArray(), static fn (mixed $on) => true === $on))));
    }

    private function locale(?string $language): string
    {
        $language = str_replace('_', '-', $language ?? $this->locale);

        return match (true) {
            str_contains($language, '-') => $language,
            'en' === $language => 'en-US',
            default => strtolower($language).'-'.strtoupper($language),
        };
    }
}
