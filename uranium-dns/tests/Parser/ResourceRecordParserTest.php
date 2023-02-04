<?php

namespace Cijber\Tests\Uranium\Dns\Parser;

use Cijber\Uranium\Dns\Parser\MessageParser;
use Cijber\Uranium\Dns\Parser\RecordParser;
use Cijber\Uranium\Dns\Record\A;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;
use PHPUnit\Framework\TestCase;


class ResourceRecordParserTest extends TestCase {
    function testSimpleParse() {
        $data   = "\x06\x67\x61\x6d\x65\x72\x73\x03\x63\x6f\x6d\x00\x00\x01\x00\x01\x00\x00\x0e\x10\x00\x04\xdb\xea\x58\xe3";
        $record = RecordParser::parseResource($data);
        $this->assertInstanceOf(A::class, $record);
        $this->assertEquals(ResourceType::A, $record->type);
        $this->assertEquals(ResourceClass::IN, $record->class);
        $this->assertEquals("219.234.88.227", (string)$record->getAddress());
        $this->assertEquals(3600, $record->ttl);
    }
}
