<?php

namespace Omnigame\Gog\Tests;

use Omnigame\Gog\GogProvider;
use Omnigame\Testing\BareTestCase;

/** omnigame/gog's classes load with omniscient/omnigame and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return GogProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }
}
