<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profileer\Profilee;
use Symfony\Component\HttpKernel\Profileer\Profileer;

/**
 * ProfileerListener collects data for the current request by listening to the kernel events.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ProfileerListener implements EventSubscriberInterface
{
    private Profileer $profileer;
    private ?RequestMatcherInterface $matcher;
    private bool $onlyException;
    private bool $onlyMainRequests;
    private ?\Throwable $exception = null;
    /** @var \SplObjectStorage<Request, Profilee> */
    private \SplObjectStorage $profilees;
    private RequestStack $requestStack;
    private ?string $collectParameter;
    /** @var \SplObjectStorage<Request, Request|null> */
    private \SplObjectStorage $parents;

    /**
     * @param bool $onlyException    True if the profileer only collects data when an exception occurs, false otherwise
     * @param bool $onlyMainRequests True if the profileer only collects data when the request is the main request, false otherwise
     */
    public function __construct(Profileer $profileer, RequestStack $requestStack, ?RequestMatcherInterface $matcher = null, bool $onlyException = false, bool $onlyMainRequests = false, ?string $collectParameter = null)
    {
        $this->profileer = $profileer;
        $this->matcher = $matcher;
        $this->onlyException = $onlyException;
        $this->onlyMainRequests = $onlyMainRequests;
        $this->profilees = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();
        $this->requestStack = $requestStack;
        $this->collectParameter = $collectParameter;
    }

    /**
     * Handles the onKernelException event.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->onlyMainRequests && !$event->isMainRequest()) {
            return;
        }

        $this->exception = $event->getThrowable();
    }

    /**
     * Handles the onKernelResponse event.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->onlyMainRequests && !$event->isMainRequest()) {
            return;
        }

        if ($this->onlyException && null === $this->exception) {
            return;
        }

        $request = $event->getRequest();
        if (null !== $this->collectParameter && null !== $collectParameterValue = $request->get($this->collectParameter)) {
            true === $collectParameterValue || filter_var($collectParameterValue, \FILTER_VALIDATE_BOOL) ? $this->profileer->enable() : $this->profileer->disable();
        }

        $exception = $this->exception;
        $this->exception = null;

        if (null !== $this->matcher && !$this->matcher->matches($request)) {
            return;
        }

        $session = !$request->attributes->getBoolean('_stateless') && $request->hasPreviousSession() ? $request->getSession() : null;

        if ($session instanceof Session) {
            $usageIndexValue = $usageIndexReference = &$session->getUsageIndex();
            $usageIndexReference = \PHP_INT_MIN;
        }

        try {
            if (!$profilee = $this->profileer->collect($request, $event->getResponse(), $exception)) {
                return;
            }
        } finally {
            if ($session instanceof Session) {
                $usageIndexReference = $usageIndexValue;
            }
        }

        $this->profilees[$request] = $profilee;

        $this->parents[$request] = $this->requestStack->getParentRequest();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        // attach children to parents
        foreach ($this->profilees as $request) {
            if (null !== $parentRequest = $this->parents[$request]) {
                if (isset($this->profilees[$parentRequest])) {
                    $this->profilees[$parentRequest]->addChild($this->profilees[$request]);
                }
            }
        }

        // save profilees
        foreach ($this->profilees as $request) {
            $this->profileer->saveProfilee($this->profilees[$request]);
        }

        $this->profilees = new \SplObjectStorage();
        $this->parents = new \SplObjectStorage();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }
}
