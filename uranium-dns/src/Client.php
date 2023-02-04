<?php

namespace Cijber\Uranium\Dns;

use Cijber\Uranium\Dns\Parser\SystemConfig;
use Cijber\Uranium\Dns\Record\A;
use Cijber\Uranium\Dns\Record\AAAA;
use Cijber\Uranium\Dns\Record\CNAME;
use Cijber\Uranium\Dns\Resolver\Handler\FailOver;
use Cijber\Uranium\Dns\Resolver\Handler\Onion;
use Cijber\Uranium\Dns\Resolver\Middleware\Cache;
use Cijber\Uranium\Dns\Resolver\Middleware\Logger;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Source\Forward;
use Cijber\Uranium\Dns\Resolver\Source\HostsFile;
use Cijber\Uranium\Dns\Resolver\Source\Nuller;
use Cijber\Uranium\Dns\Resolver\Source\Recursor;
use Cijber\Uranium\Dns\Resolver\Source\Zone;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\Resolver\StackBuilder;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\IO\Filesystem;
use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\Loop;
use Kdl\Kdl\Document;
use Kdl\Kdl\Kdl;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Stash\Driver\Ephemeral;
use Stash\Pool;


class Client implements LoopAwareInterface, LoggerAwareInterface
{
    use LoopAwareTrait;
    use LoggerAwareTrait;


    static array $instances = [];

    public static function instance(?Loop $loop = null): Client
    {
        $loop ??= Loop::get();
        $id   = spl_object_id($loop);

        if ( ! isset(static::$instances[$id])) {
            static::$instances[$id] = Client::default($loop);
        }

        return static::$instances[$id];
    }

    private SessionManager $sessionManager;

    public function __construct(
      private Stack $stack,
      ?SessionManager $sessionManager = null,
      ?Loop $loop = null,
    ) {
        $this->loop           = $loop ?: Loop::get();
        $this->sessionManager = $sessionManager ?: SessionManager::get($this->loop);

        $this->setLogger($this->loop->getLogger());
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;

        $this->stack->setLogger($logger);
    }

    public static function nameservers(array $nameservers = [], ?Loop $loop = null): Client
    {
        $loop           ??= Loop::get();
        $sessionManager = SessionManager::get($loop);

        return new Client(new Stack(new Forward($nameservers, $sessionManager)), $sessionManager, $loop);
    }

    public static function system(?Loop $loop = null): Client
    {
        $resolvers = SystemConfig::fetchSystemResolvers();
        $loop      ??= Loop::get();

        $sessionManager = SessionManager::get($loop);

        $failOver = new FailOver(
          [
            new HostsFile(),
            new Forward($resolvers, $sessionManager, $loop),
          ]
        );

        return new Client(new Stack($failOver), $sessionManager, $loop);
    }

    public static function default(?Loop $loop = null): Client
    {
        $loop ??= Loop::get();

        $stack   = new Stack(new Nuller());
        $hosts   = new HostsFile();
        $forward = new Forward(SystemConfig::fetchSystemResolvers(), $stack->getSessionManager(), $loop);

        $failover = new FailOver([$hosts, $forward]);
        $logger   = new Logger();
        $cache    = new Cache(new  Pool(new Ephemeral(["maxItems" => 255])));
        $onion    = new Onion($failover, [$logger, $cache]);
        $onion->setLoop($loop);

        $stack->setHandler($onion);

        return new Client($stack);
    }

    public static function recursive(?Loop $loop = null): Client
    {
        $loop ??= Loop::get();

        $stack = new Stack(new Nuller());
        $zone  = new Zone();
        $zone->addZoneFile(__DIR__ . "/../config/hints.zone");
        $recursor = new Recursor();
        $recursor->setZone($zone);
        $failover = new FailOver([$zone, $recursor]);

        $logger = new Logger();
        $cache  = new Cache(new  Pool(new Ephemeral(["maxItems" => 255])));
        $onion  = new Onion($failover, [$logger, $cache]);
        $onion->setLoop($loop);

        $stack->setHandler($onion);

        return new Client($stack);
    }

    public static function fromKdlFile(string $file, ?Loop $loop = null): Client
    {
        return static::fromKdl(Filesystem::slurp($file, $loop), dirname($file), $loop);
    }

    public static function fromKdl(string $kdl, ?string $cwd = null, ?Loop $loop = null): Client
    {
        /** @var Document $document */
        $document = Kdl::parse($kdl);
        $stack    = StackBuilder::fromConfig($document->jsonSerialize()[0] ?? [], $cwd, $loop);

        return new Client($stack, loop: $loop);
    }

    /**
     * @param  int  $type
     * @param  string|array  $domain
     * @param  int  $class
     * @param  bool  $recursion
     * @param  Address|null  $responder
     * @param  Message|null  $response
     *
     * @return ResourceRecord[]
     */
    public function get(int $type, string|array $domain, int $class = ResourceClass::IN, bool $recursion = true, ?Address &$responder = null, ?Message &$response = null): array
    {
        if (is_string($domain)) {
            $domain = explode(".", rtrim($domain, "."));
        }

        $message  = new Message(0, false, Message::OP_QUERY, requestRecords: [new QuestionRecord($domain, $type, $class)], recursionDesired: $recursion);
        $response = $this->stack->request($message);

        return $response?->getResponseRecords() ?: [];
    }

    /**
     * @param  string|array  $domain
     *
     * @return Address[]
     */
    public function getAddress(string|array $domain): array
    {
        if (is_string($domain)) {
            $domain = explode(".", rtrim($domain, "."));
        }

        $addresses = [];
        $options   = $this->loop->race([ResourceType::AAAA, ResourceType::A], fn($type) => $this->get($type, $domain, response: $response));
        foreach ($options as $rrs) {
            foreach ($rrs as $rr) {
                if ($rr instanceof A || $rr instanceof AAAA) {
                    $addresses[] = $rr->getAddress();
                }
            }
        }

        return $addresses;
    }

    public function resolve(Address|string $address): array
    {
        if ($address instanceof Address) {
            return [$address];
        }

        $parts = inet_pton($address);
        if ($parts !== false) {
            return [Address::fromBytes($parts)];
        }

        return $this->getAddress($address);
    }

    public function request(Message $message): Message
    {
        return $this->stack->request($message);
    }
}