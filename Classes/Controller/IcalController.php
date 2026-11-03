<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Controller;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Repository\EventRepository;
use Greenfieldr\GolbEvents\Export\IcalBuilder;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Delivers the current event page as iCalendar file. Rendered through the
 * page type "golbEventsIcal" (see the TypoScript setup), e.g.
 * /events/conference?type=1764 or /events/conference/event.ics with a
 * PageType route enhancer.
 */
class IcalController extends ActionController
{
    public function __construct(
        protected readonly EventRepository $eventRepository,
        protected readonly IcalBuilder $icalBuilder,
        protected readonly Context $context,
    ) {}

    public function exportAction(): ResponseInterface
    {
        $pageInformation = $this->request->getAttribute('frontend.page.information');
        $uid = $pageInformation instanceof PageInformation ? $pageInformation->getId() : 0;
        $event = $uid > 0 ? $this->eventRepository->findByUid($uid) : null;
        $ical = null;
        if ($event instanceof Event) {
            $url = $this->uriBuilder->reset()->setTargetPageUid($uid)->setCreateAbsoluteUri(true)->build();
            $ical = $this->icalBuilder->build($event, $url, $this->host(), $this->now());
        }

        if ($ical === null) {
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction($this->request, 'The page is not an event.'),
                1762171201
            );
        }

        $fileName = preg_replace('/[^a-z0-9]+/', '-', strtolower($event->getTitle())) ?? 'event';

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . trim($fileName, '-') . '.ics"')
            ->withBody($this->streamFactory->createStream($ical));
    }

    private function host(): string
    {
        $site = $this->request->getAttribute('site');
        $host = $site instanceof Site ? $site->getBase()->getHost() : '';

        return $host !== '' ? $host : $this->request->getUri()->getHost();
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
