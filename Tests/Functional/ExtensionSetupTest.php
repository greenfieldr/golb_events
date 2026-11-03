<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies that the extension boots in a TYPO3 instance together with its
 * dependency Golb.
 */
#[CoversNothing]
final class ExtensionSetupTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    #[Test]
    public function extensionIsLoadedTogetherWithGolb(): void
    {
        self::assertTrue(ExtensionManagementUtility::isLoaded('golb'));
        self::assertTrue(ExtensionManagementUtility::isLoaded('golb_events'));
    }
}
