<?php

namespace Cijber\Uranium\Compat\Tests;

use Cijber\Uranium\Compat\UraniumLoop;
use Cijber\Uranium\IO\PhpStream;
use Cijber\Uranium\Uranium;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Promise\Deferred;

use React\Stream\DuplexResourceStream;

use function Cijber\Uranium\Compat\await;


class CompatTest extends TestCase
{
    public function testTimer()
    {
        Loop::set(new UraniumLoop());

        $now = time();

        Loop::addTimer(5, function () use ($now) {
            $then = time();
            $this->assertEquals(5, floor($then - $now));
        });

        Loop::run();
    }

    public function testPromise()
    {
        Loop::set(new UraniumLoop());

        $now = time();

        $defer = new Deferred();

        Loop::addTimer(5, function () use ($defer) {
            $then = time();
            $defer->resolve($then);
        });

        $promise = $defer->promise();
        Uranium::queue(function () use ($promise, $now) {
            $then = await($promise);
            $this->assertEquals(5, floor($then - $now));
        });

        Loop::run();
    }

    public function testStream()
    {
        Loop::set(new UraniumLoop());

        [$left, $right] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $reactStream   = new DuplexResourceStream($left);
        $uraniumStream = new PhpStream($right);

        $reactRead   = null;
        $uraniumRead = null;
        $closeCalled = false;

        $reactStream->on('data', function ($data) use (&$reactRead, $reactStream) {
            $reactRead = $data;
            $reactStream->end();
        });

        $reactStream->on('close', function () use ($uraniumStream, &$closeCalled) {
            $closeCalled = true;
        });

        Loop::futureTick(function () use ($reactStream) {
            $reactStream->write("Bye world!\n");
        });


        Uranium::queue(function () use ($uraniumStream, &$uraniumRead) {
            $uraniumStream->write("Hello world!\n");
            $uraniumStream->flush();
            $uraniumRead = $uraniumStream->read();
            $uraniumStream->close();
        });

        Loop::run();

        $this->assertEquals("Bye world!\n", $uraniumRead);
        $this->assertEquals("Hello world!\n", $reactRead);
        $this->assertTrue($uraniumStream->eof());
        $this->assertTrue($closeCalled);
    }
}