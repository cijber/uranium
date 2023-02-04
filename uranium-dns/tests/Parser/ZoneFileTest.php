<?php

namespace Cijber\Tests\Uranium\Dns\Parser;

use Cijber\Uranium\Dns\Client;
use Cijber\Uranium\Dns\Resolver\Source\Zone;
use Cijber\Uranium\Dns\Resolver\Stack;
use PHPUnit\Framework\TestCase;


class ZoneFileTest extends TestCase {
    public function testX() {
        $zoneFile = <<<HERE
\$ORIGIN MIT.EDU.
@   IN  SOA     VENERA      Action\.domains (
                                 20     ; SERIAL
                                 7200   ; REFRESH
                                 600    ; RETRY
                                 3600000; EXPIRE
                                 60)    ; MINIMUM

        NS      A.ISI.EDU.
        NS      VENERA
        NS      VAXA
        MX      10      VENERA
        MX      20      VAXA

A       A       26.3.0.103

VENERA  A       10.1.0.52
        A       128.9.0.32

VAXA    A       10.2.0.27
        A       128.9.0.33
HERE;


        $zone = new Zone();
        $zone->addZone($zoneFile);

        $client = new Client(new Stack($zone));

        $addresses = $client->getAddress("venera.mit.edu");
        $txt       = [];
        foreach ($addresses as $address) {
            $txt[] = (string)$address;
        }


        $this->assertEquals(["10.1.0.52", "128.9.0.32"], $txt);
    }
}
