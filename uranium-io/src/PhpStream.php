<?php

namespace Cijber\Uranium\IO;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Utils\Hacks;
use Cijber\Uranium\Waker\StreamWaker;
use RuntimeException;


class PhpStream extends Stream
{
    private bool $closed = false;

    /**
     * @var StreamWaker[]
     */
    private array $readWakers = [];
    /**
     * @var StreamWaker[]
     */
    private array $writeWakers = [];

    public function __construct(protected $stream, ?Loop $loop = null)
    {
        $this->loop = $loop ?: Loop::get();

        if (stream_set_blocking($this->stream, false) !== true) {
            throw new RuntimeException("Failed to make stream non-blocking");
        }

        stream_set_read_buffer($this->stream, 0);
        stream_set_write_buffer($this->stream, 0);
    }

    public function eof(): bool
    {
        return ! is_resource($this->stream) || feof($this->stream);
    }

    public function waitReadable(): bool
    {
        if ($this->eof()) {
            return false;
        }

        $waker              = StreamWaker::read($this->stream);
        $this->readWakers[] = $waker;
        $this->loop->suspend($waker);
        $idx = array_search($waker, $this->readWakers);
        array_splice($this->readWakers, $idx, 1, []);

        return $this->eof();
    }

    public function read(int $size = 4096): string
    {
        while ( ! $this->eof()) {
            $this->waitReadable();

            if ($this->eof()) {
                break;
            }

            $data = Hacks::errorHandler(fn() => stream_get_contents($this->stream, $size), $error);

            if ($error !== null) {
                throw new RuntimeException("Failed to read", previous: $error);
            }

            if ($data !== "" || $this->eof()) {
                return $data;
            }
        }

        return "";
    }

    public function waitWritable(): bool
    {
        if ($this->eof()) {
            return false;
        }

        $waker               = StreamWaker::write($this->stream);
        $this->writeWakers[] = $waker;
        $this->loop->suspend($waker);
        $idx = array_search($waker, $this->writeWakers);
        array_splice($this->writeWakers, $idx, 1, []);

        return $this->eof();
    }

    public function write(string $data, ?int $size = null): int
    {
        $size = $size ?? strlen($data);

        while ( ! $this->eof()) {
            $this->waitWritable();
            $writtenNow = Hacks::errorHandler(fn() => fwrite($this->stream, $data, $size), $error);

            if (($writtenNow === 0 || $writtenNow === false) && $error !== null) {
                throw new RuntimeException("Failed to write", previous: $error);
            }

            if ($writtenNow > 0) {
                return $writtenNow;
            }
        }

        return 0;
    }

    public function close()
    {
        fclose($this->stream);
        $this->closed = true;

        foreach (array_merge($this->readWakers, $this->writeWakers) as $waker) {
            $this->loop->removeWaker($waker);
            $this->loop->wake($waker);
        }
    }

    public function __destruct()
    {
        if ( ! $this->closed) {
            $this->close();
        }
    }

    public function flush()
    {
        if ( ! is_resource($this->stream)) {
            return;
        }

        fflush($this->stream);
    }
}