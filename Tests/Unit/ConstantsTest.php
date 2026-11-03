<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit;

use Greenfieldr\Golb\Constants as GolbConstants;
use Greenfieldr\GolbEvents\Constants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Constants::class)]
final class ConstantsTest extends UnitTestCase
{
    #[Test]
    public function eventDoktypeDoesNotCollideWithCoreOrGolbDoktypes(): void
    {
        $takenDoktypes = [GolbConstants::BLOG_POST_DOKTYPE];
        foreach ((new \ReflectionClass(PageRepository::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'DOKTYPE_') && is_int($value)) {
                $takenDoktypes[] = $value;
            }
        }

        self::assertGreaterThan(1, count($takenDoktypes), 'Core doktype constants were not found');
        self::assertNotContains(Constants::EVENT_DOKTYPE, $takenDoktypes);
    }
}
