<?php

namespace Cijber\Uranium\Dns\Resolver\Source;

use Cijber\Collections\BTreeMap;
use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\QuestionRecord;
use Cijber\Uranium\Dns\Record\A;
use Cijber\Uranium\Dns\Record\AAAA;
use Cijber\Uranium\Dns\Record\NS;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Source;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\Dns\Session\InternalSession;
use Cijber\Uranium\Dns\SessionManager;
use Cijber\Uranium\Loop;
use JetBrains\PhpStorm\Pure;


class Recursor extends Source
{
    private BTreeMap $nameserverAddresses;
    private BTreeMap $nameserversByDomain;
    private SessionManager $manager;

    private InternalSession $session;

    private Zone $zone;

    #[Pure]
    public function __construct()
    {
        $this->nameserverAddresses = new BTreeMap();
        $this->nameserversByDomain = new BTreeMap();
        $this->session             = new InternalSession("recursor");
        $this->zone                = new Zone();
    }

    public function setStack(Stack $stack): void
    {
        $this->stack   = $stack;
        $this->manager = $stack->getSessionManager();
    }

    public function setManager(SessionManager $manager): void
    {
        $this->manager = $manager;
    }

    function handle(Request $request): ?Response
    {
        $question = $request->message->getQuestion();

        if ($question === null) {
            return null;
        }

        $ns = $this->nameserversByDomain->get($question->labels, $found);

        if ( ! $found) {
            $req          = clone $request;
            $req->message = clone $req->message;
            $req->message->setRecursionDesired(false);
            $resp = $this->zone->handle($req);

            if (count($resp->message->getAuthoritativeRecords()) > 0) {
                $this->handleNonAnswer($resp->message);

                $currentLevel = $resp->message->getAuthoritativeRecords()[0]->getLabels();

                while ($currentLevel !== $question->labels) {
                    $ns = $this->nameserversByDomain->get($currentLevel, $found);

                    if ( ! $found) {
                        break;
                    }

                    do {
                        $addrs    = [];
                        $all      = true;
                        $notFound = [];
                        foreach ($ns as $label) {
                            $addr = $this->nameserverAddresses->get($label, $found);

                            if ( ! $found) {
                                $all        = false;
                                $notFound[] = $label;
                                continue;
                            }

                            foreach ($addr as $add) {
                                $addrs[] = [$add, implode(".", $label)];
                            }
                        }

                        if (count($addrs) > 0) {
                            $respIter = $this->loop->race($addrs, function ($input) use ($question) {
                                $tries  = 5;
                                $result = null;

                                while ($tries-- > 0) {
                                    $result = $this->manager->request(new Message(0, false, Message::OP_QUERY, requestRecords: [$question], recursionDesired: false), $input[0], $input[1]);

                                    if ($result !== null) {
                                        break;
                                    }

                                    $this->loop->sleep(1);
                                }

                                return $result;
                            }, 3)
                                                   ->filter(fn($d) => $d !== null);

                            /** @var Message $message */
                            foreach ($respIter as $message) {
                                if (count($message->responseRecords) === 0 && count($message->authoritativeRecords) > 0) {
                                    $this->handleNonAnswer($message);
                                    $currentLevel = $message->getAuthoritativeRecords()[0]->getLabels();
                                    continue 3;
                                }

                                if (count($message->responseRecords) > 0) {
                                    return Response::ok($request, $message->responseRecords);
                                }
                            }
                        }

                        if ($all) {

                            return Response::nxdomain($request);
                        }

                        foreach ($notFound as $label) {
                            if ($label === $question->labels) {
                                continue;
                            }

                            $result = $this->stack->request(new Message(0, false, Message::OP_QUERY, requestRecords: [new QuestionRecord($label, ResourceType::A, ResourceClass::IN)], recursionDesired: true));

                            $addrs = [];
                            if ($result->isResponse() && ! $result->isNotFound()) {
                                foreach ($result->getResponseRecords() as $record) {
                                    if ($record instanceof A || $record instanceof AAAA) {
                                        $addrs[] = $record->getAddress();
                                    }
                                }
                            }

                            $this->nameserverAddresses->set($label, $addrs);
                        }
                    } while ( ! $all);
                }

                $ns = $this->nameserversByDomain->get($question->labels, $found);
            }
        }

        return Response::nxdomain($request);
    }

    private function handleNonAnswer(Message $message)
    {
        $rrs          = $message->getAuthoritativeRecords();
        $labels       = [];
        $byLabel      = [];
        $foundDomains = [];
        foreach ($rrs as $rr) {
            if ($rr->class == ResourceClass::IN && $rr->type == ResourceType::NS && $rr instanceof NS) {
                $labels[$rr->getLabelString()]        = $rr->getLabels();
                $byLabel[$rr->getLabelString()]       ??= [];
                $byLabel[$rr->getLabelString()][]     = $rr->getDomain();
                $foundDomains[$rr->getDomainString()] = true;
            }
        }

        $nameserverAddresses = [];
        $nameserverLabels    = [];
        foreach ($message->getAdditionalRecords() as $rr) {
            if ($rr->class == ResourceClass::IN && in_array($rr->type, [ResourceType::A, ResourceType::AAAA]) && ($rr instanceof A || $rr instanceof AAAA) && isset($foundDomains[$rr->getLabelString()])) {
                $nameserverLabels[$rr->getLabelString()]      = $rr->getLabels();
                $nameserverAddresses[$rr->getLabelString()]   ??= [];
                $nameserverAddresses[$rr->getLabelString()][] = $rr->getAddress();
            }
        }

        foreach ($byLabel as $k => $ns) {
            $this->nameserversByDomain->set($labels[$k], $ns);
        }

        foreach ($nameserverAddresses as $k => $ns) {
            $this->nameserverAddresses->set($nameserverLabels[$k], $ns);
        }
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, Loop $loop): static
    {
        $recursor = new static();

        foreach ($config["values"] as $value) {
            if ( ! str_starts_with($value, "/")) {
                $value = $cwd . '/' . $value;
            }

            $recursor->zone->addZoneFile($value);
        }

        if (count($config['values']) === 0) {
            $recursor->zone->addZoneFile(__DIR__ . '/../../../config/hints.zone');
        }
    }

    public function setZone(Zone $zone): void
    {
        $this->zone = $zone;
    }
}