<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Session\InternalSession;
use Cijber\Uranium\Dns\Session\Session;
use Cijber\Uranium\Dns\SessionManager;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Duration;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;


class Stack implements LoopAwareInterface, LoggerAwareInterface
{
    use LoopAwareTrait;
    use LoggerAwareTrait;


    private InternalSession $session;

    private SessionManager $sessionManager;

    public function getSessionManager(): mixed
    {
        return $this->sessionManager;
    }

    public function __construct(private Handler $handler, ?SessionManager $sessionManager = null, ?Loop $loop = null)
    {
        $this->loop           = $loop ?: Loop::get();
        $this->sessionManager = $sessionManager ?: SessionManager::get($this->loop);
        $this->session        = new InternalSession();
    }

    public function setHandler(Handler $handler): void
    {
        $this->handler = $handler;
        $handler->setStack($this);
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->handler->setLogger($logger);
    }

    public function request(Message $message, ?Session $session = null, bool $localOnly = false): Message
    {
        return $this->handle(new Request($message, $session ?: $this->session, $localOnly))->message;
    }

    public function handle(Request $request): Response
    {
        $resp = $this->loop->timeout(Duration::seconds(5), fn() => $this->handler->handle($request));

        return $resp ?: Response::nxdomain($request);
    }
}