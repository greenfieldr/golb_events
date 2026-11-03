<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\DataProcessing;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Repository\EventRepository;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEvents\Schema\EventSchemaBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Provides the event of the currently rendered page and its schema.org
 * structured data to Fluid, e.g. in a page template:
 *
 *   dataProcessing.10 = Greenfieldr\GolbEvents\DataProcessing\EventSchemaProcessor
 *   dataProcessing.10.as = event
 *   dataProcessing.10.schemaAs = eventSchema
 *
 * {event} is the Event object (null for pages that are no events),
 * {eventSchema} the structured data as array and {eventSchemaJson} as JSON
 * string for a <script type="application/ld+json"> tag. The next
 * occurrence (or the last one for past events) provides the dates.
 */
class EventSchemaProcessor implements DataProcessorInterface
{
    public function __construct(
        protected readonly EventRepository $eventRepository,
        protected readonly OccurrenceRepository $occurrenceRepository,
        protected readonly EventSchemaBuilder $schemaBuilder,
        protected readonly Context $context,
    ) {}

    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array<mixed> $contentObjectConfiguration The configuration of Content Object
     * @param array<mixed> $processorConfiguration The configuration of this processor
     * @param array<mixed> $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array<mixed> the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $eventVariable = (string)$cObj->stdWrapValue('as', $processorConfiguration, 'event');
        $schemaVariable = (string)$cObj->stdWrapValue('schemaAs', $processorConfiguration, 'eventSchema');

        $uid = (int)($cObj->data['uid'] ?? 0);
        $event = $uid > 0 ? $this->eventRepository->findByUid($uid) : null;
        $schema = [];
        if ($event instanceof Event) {
            $occurrence = $this->occurrenceRepository->findNextOfEvent($event, $this->now());
            $url = $cObj->typoLink_URL(['parameter' => $uid, 'forceAbsoluteUrl' => true]);
            $schema = $this->schemaBuilder->build($event, $occurrence, $url);
        }

        $processedData[$eventVariable] = $event instanceof Event ? $event : null;
        $processedData[$schemaVariable] = $schema;
        $processedData[$schemaVariable . 'Json'] = $schema === []
            ? ''
            : json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $processedData;
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
