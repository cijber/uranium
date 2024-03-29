<?php

namespace Cijber\Uranium\Dns\Resolver\Source;

use Cijber\Uranium\Dns\Internal\Database;
use Cijber\Uranium\Dns\Parser\ZoneFile;
use Cijber\Uranium\Dns\QuestionRecord;
use Cijber\Uranium\Dns\Record\NS;
use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Source;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\Loop;


class Zone extends Source
{
    private Database $database;
    private bool $authoritative = false;

    public function __construct()
    {
        $this->database = new Database();
    }

    /**
     * @param bool $authoritative
     */
    public function setAuthoritative(bool $authoritative): void
    {
        $this->authoritative = $authoritative;
    }

    public function addZoneFile(string $filename, string|array|null $origin = null)
    {
        $records = ZoneFile::read($filename, $origin);

        foreach ($records as $record) {
            $this->database->add($record);
        }
    }

    public function addZone(string $data, string|array|null $origin = null)
    {
        $records = ZoneFile::parse($data, $origin);

        foreach ($records as $record) {
            $this->database->add($record);
        }
    }

    function handle(Request $request): ?Response
    {
        $question = $request->message->getQuestion();
        if ($question === null) {
            return null;
        }

        $rrs = $this->database->get($question, $found);

        if ($found && count($rrs) > 0) {
            return Response::ok($request, $rrs);
        }

        if ($this->authoritative) {
            $soaReq = clone $question;
            $soaReq->type = ResourceType::SOA;

            while (count($soaReq->labels) > 0) {
                $rrs = $this->database->get($soaReq, $found);

                if ($found && count($rrs) > 0) {
                    $nxdomain = Response::nxdomain($request);
                    $nxdomain->message->setAuthoritativeRecords($rrs);
                    return $nxdomain;
                }

                $soaReq->setLabels(array_slice($soaReq->labels, 1));
            }
        }

        if ($request->message->isRecursionDesired()) {
            $nsQuestion = clone $question;
            $nsQuestion->type = ResourceType::NS;

            while (count($nsQuestion->labels) > 0) {
                $nsQuestion->setLabels(array_slice($nsQuestion->labels, 1));
                $rrs = $this->database->get($nsQuestion, $found);

                if ($found && count($rrs) > 0) {
                    $addrrs = [];

                    /** @var NS $ns */
                    foreach ($rrs as $ns) {
                        $addrrs = array_merge($addrrs, $this->database->get(new QuestionRecord($ns->getDomain(), ResourceType::ANY, ResourceClass::IN)));
                    }

                    return Response::ok($request, authoritativeRecords: $rrs, additionalRecords: $addrrs);
                }
            }
        }

        return null;
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, Loop $loop): static
    {
        $zone = new static();

        if (isset($config["properties"]->auth) && $config["properties"]->auth) {
            $zone->authoritative = true;
        }

        foreach ($config["values"] as $value) {
            if (!str_starts_with($value, "/")) {
                $value = $cwd . '/' . $value;
            }

            $zone->addZoneFile($value);
        }

        return $zone;
    }
}