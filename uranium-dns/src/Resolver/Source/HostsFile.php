<?php

namespace Cijber\Uranium\Dns\Resolver\Source;

use Cijber\Uranium\Dns\Parser\SystemConfig;
use Cijber\Uranium\Dns\Record\A;
use Cijber\Uranium\Dns\Record\AAAA;
use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Source;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;


class HostsFile extends Source {
    private array $hosts;

    public function __construct(public string $source = "/etc/hosts") {
        $this->hosts = SystemConfig::fetchStaticHosts($this->source);
    }

    function handle(Request $request, ?Handler $failOver = null): ?Response {
        $question = $request->message->getQuestion();
        if ($question === null || $question->class !== ResourceClass::IN || ! in_array($question->type, [ResourceType::A, ResourceType::AAAA])) {
            return null;
        }

        $items = $this->hosts[$question->getLabelString()] ?? null;

        if ($items === null) {
            return null;
        }

        $rrs = [];

        foreach ($items as $item) {
            if ($item->getVersion() === 4 && $question->type === ResourceType::A) {
                $rrs[] = A::create($question->getLabels(), $item);
            }

            if ($item->getVersion() === 6 && $question->type === ResourceType::AAAA) {
                $rrs[] = AAAA::create($question->getLabels(), $item);
            }
        }

        return Response::ok($request, $rrs);
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static {
        return new HostsFile($config["values"][0] ?? "/etc/hosts");
    }
}