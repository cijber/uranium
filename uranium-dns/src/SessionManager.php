<?php

namespace Cijber\Uranium\Dns;

use Cijber\Uranium\Dns\Internal\SessionTracker;
use Cijber\Uranium\Dns\Session\Session;
use Cijber\Uranium\Dns\Session\StreamSession;
use Cijber\Uranium\Dns\Session\UdpSession;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Waker\ManualWaker;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;


class SessionManager implements LoopAwareInterface, LoggerAwareInterface
{
    use LoopAwareTrait;
    use LoggerAwareTrait;


    static array $instances = [];

    public static function get(?Loop $loop = null)
    {
        $loop ??= Loop::get();

        $id = spl_object_id($loop);
        if ( ! isset(static::$instances[$id])) {
            static::$instances[$id] = new SessionManager($loop);
        }

        return static::$instances[$id];
    }

    public int $id = 0;

    private array $openRequests = [];

    private array $sessions = [];
    /**
     * @var SessionTracker[]
     */
    private array $sessionInfo = [];
    private array $responses = [];

    public function __construct(
      ?Loop $loop = null,
    ) {
        $this->loop   = $loop ?: Loop::get();
        $this->logger = $this->loop->getLogger();
    }

    public function getNextId(): int
    {
        $start = $this->id;
        do {
            if ($this->id > 65535) {
                $this->id = 0;
            }

            $id = $this->id++;

            if ($start === $this->id) {
                throw new RuntimeException("Depleted request ids");
            }
        } while (isset($this->openRequests[$id]));

        return $id;
    }

    public function request(Message $message, Address $nameserver, ?string $ns = null): ?Message
    {
        $message = clone $message;

        $message->setId($this->getNextId());
        $waker = new ManualWaker();

        $data = $message->toBytes();

        $session = $this->getSessionFor($nameserver, strlen($data));
        $id      = spl_object_id($session);

        if ( ! isset($this->openRequests[$id])) {
            $this->openRequests[$id] = [];
        }

        $this->openRequests[$id][$message->getId()] = $waker;

        $question = $message->getQuestion();
        if ($question !== null) {
            $l = $ns ?: $nameserver;
            $this->logger?->debug("Requesting Message[{$message->getId()}] from {$l}, Question: {$question} ");
        }

        $session->write($message);
        $this->addReader($session);
        $this->loop->after(Duration::seconds(2), function () use ($message, $id) {
            if (isset($this->openRequests[$id][$message->getId()])) {
                $this->openRequests[$id][$message->getId()]->wake();
            }
        });
        $this->loop->suspend($waker);
        unset($this->openRequests[$id][$message->getId()]);
        $this->removeReader($session);
        if (isset($this->responses[$id][$message->getId()])) {
            $response = $this->responses[$id][$message->getId()];
            unset($this->responses[$id][$message->getId()]);

            return $response;
        }

        return null;
    }

    public function getSessionFor(Address $nameserver, int $size): Session
    {
        $nameserverHandle = $nameserver->url();
        if ( ! isset($this->sessions[$nameserverHandle])) {
            $this->sessions[$nameserverHandle] = [];
        }

        $type = $size <= 512 ? 'udp' : 'tcp';

        if ( ! isset($this->sessions[$nameserverHandle][$type])) {
            $session = $this->sessions[$nameserverHandle][$type] = match ($type) {
                'udp' => UdpSession::connect($nameserver),
                'tcp' => StreamSession::connectTcp($nameserver),
            };

            $this->sessionInfo[spl_object_id($session)] = new SessionTracker($nameserverHandle, $type);
        }

        return $this->sessions[$nameserverHandle][$type];
    }

    private function addReader(Session $session)
    {
        $id = spl_object_id($session);
        $this->sessionInfo[$id]->inc();

        if ($this->sessionInfo[$id]->connections() === 1) {
            $this->loop->queue(function () use ($session, $id) {
                while ($this->sessionInfo[$id]->connections() > 0) {
                    $message = $session->read();
                    if ($message === null) {
                        foreach ($this->openRequests[$id] as $waker) {
                            $this->loop->wake($waker);
                            $this->openRequests[$id] = [];
                        }

                        break;
                    }

                    if ( ! isset($this->openRequests[$id][$message->getId()])) {
                        continue;
                    }

                    $this->responses[$id][$message->getId()] = $message;
                    $this->loop->wake($this->openRequests[$id][$message->getId()]);
                }
            });
        }
    }

    private function removeReader(Session $session)
    {
        $id = spl_object_id($session);
        if ( ! isset($this->sessionInfo[$id])) {
            return;
        }

        $this->sessionInfo[$id]->dec();

        if ($this->sessionInfo[$id] === 0) {
            $this->loop->after(Duration::seconds(3), function () use ($session, $id) {
                if ( ! $session->isClosed() && $this->sessionInfo[$id]->connections() === 0) {
                    unset($this->sessions[$this->sessionInfo[$id]->handle()][$this->sessionInfo[$id]->type()]);
                    $session->close();
                }
            });
        }
    }
}