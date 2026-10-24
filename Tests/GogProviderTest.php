<?php

namespace Omnigame\Gog\Tests;

use Omnigame\Exception\NotSupportedException;
use Omnigame\Gog\GogFactory;
use Omnigame\Gog\GogProvider;
use Omnigame\Model\Game;
use Omnigame\Model\GameRef;
use Omnigame\Model\ImageKind;
use Omnigame\Model\Platform;
use Omnigame\Model\Query;
use Omnigame\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

/** GOG's answers recorded on 2026-10-24 (trimmed to the fields read). */
final class GogProviderTest extends TestCase
{
    public function testAProductsRecordInFrenchWithItsFrenchPrice(): void
    {
        $http = self::http();
        $game = self::provider($http)->game(GameRef::gog(1207658924), 'fr', 'FR');

        self::assertNotNull($game);
        self::assertSame(['The Witcher: Enhanced Edition', '2011-05-09', [Platform::Windows, Platform::Mac]], [$game->title, $game->released?->format('Y-m-d'), $game->platforms]);
        self::assertStringStartsWith('The Witcher est un jeu de rôles', (string) $game->summary);
        self::assertSame(['amount' => 119, 'currency' => 'EUR', 'initial' => 799, 'discount' => 85], $game->price?->toArray());
        self::assertStringStartsWith('https://images-2.gog-statics.com/', (string) $game->image(ImageKind::Logo)?->url);
        self::assertSame('https://www.gog.com/game/the_witcher', $game->url);
        self::assertSame(['expand' => 'description', 'locale' => 'fr-FR'], $http->requests[0]['options']['query'] ?? null);
        self::assertSame(['countryCode' => 'FR'], $http->requests[1]['options']['query'] ?? null);
    }

    public function testAnUnknownProductIsNull(): void
    {
        self::assertNull(self::provider(self::http())->game(GameRef::gog(9999999999)));
    }

    public function testASearchOfTheCatalogue(): void
    {
        $http = self::http();
        $games = self::provider($http, ['currency' => 'EUR'])->search(new Query('witcher', 'fr', 'FR', 2));

        self::assertSame([['The Witcher 3: Wild Hunt — Remastered', '1640424747', 2499, 4999, 50], ['The Witcher: Enhanced Edition', '1207658924', 119, 799, 85]], array_map(static fn (Game $g) => [$g->title, $g->identifiers->get('gog'), $g->price?->amount, $g->price?->initial, $g->price?->discount], $games));
        self::assertSame(['2007-10-26', ['CD PROJEKT RED'], ['Jeu de rôle', 'Action', 'Fantasy'], 4.6], [$games[1]->released?->format('Y-m-d'), $games[1]->developers, $games[1]->genres, $games[1]->ratings[0]->value ?? null]);
        self::assertSame(['query' => 'like:witcher', 'limit' => 2, 'countryCode' => 'FR', 'locale' => 'fr-FR', 'currencyCode' => 'EUR'], $http->requests[0]['options']['query'] ?? null);
    }

    public function testOnlyGogNumbersAreKnown(): void
    {
        $this->expectException(NotSupportedException::class);
        self::provider(self::http())->game(GameRef::steam(620));
    }

    private static function http(): RecordedHttpClient
    {
        $f = '@'.__DIR__.'/Fixtures/';

        return RecordedHttpClient::routes([
            '~api\.gog\.com/products/1207658924/prices~' => [200, $f.'prices-1207658924-fr.json'],
            '~api\.gog\.com/products/1207658924\?~' => [200, $f.'product-1207658924-fr.json'],
            '~catalog\.gog\.com/v1/catalog\?query=like%3Awitcher~' => [200, $f.'catalog-witcher-fr.json'],
        ]);
    }

    /** @param array<string, mixed> $options */
    private static function provider(RecordedHttpClient $http, array $options = []): GogProvider
    {
        $provider = (new GogFactory($http))->create($options);
        self::assertInstanceOf(GogProvider::class, $provider);

        return $provider;
    }
}
