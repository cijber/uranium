<?php

namespace Cijber\Uranium\IO;

use Cijber\Uranium\IO\Utils\LineReader;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\StreamWaker;


class Stream {
    const CHUNK_SIZE = 4096;

    protected Loop $loop;

    public function __construct(protected $stream, ?Loop $loop = null) {
        $this->loop = $loop ?: Loop::get();

        if (stream_set_blocking($this->stream, false) !== true) {
            throw new \RuntimeException("ah");
        }

        stream_set_read_buffer($this->stream, 0);
        stream_set_write_buffer($this->stream, 0);
    }

    public function slurp(): string {
        $data = "";
        while ( ! $this->eof()) {
            $data .= $this->read();
        }

        return $data;
    }

    public function eof(): bool {
        return feof($this->stream);
    }

    public function writeAll(string $data) {
        $toWrite = strlen($data);

        while ($toWrite > 0) {
            $nextSize = min(Stream::CHUNK_SIZE, $toWrite);
            $chunk    = $this->write($data, $nextSize);
            $toWrite  -= $chunk;
            $data     = substr($data, $chunk);
        }
    }

    public function write(string $data, ?int $size = null): int {
        $size = $size ?? strlen($data);

        while (true) {
            $this->loop->suspend(StreamWaker::write($this->stream));

            $error = null;
            set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
                $error = new \ErrorException(
                  $errstr,
                  0,
                  $errno,
                  $errfile,
                  $errline
                );
            });

            $writtenNow = fwrite($this->stream, $data);

            restore_error_handler();

            if (($writtenNow === 0 || $writtenNow === false) && $error !== null) {
                throw new \RuntimeException("Failed to write", previous: $error);
            }

            if ($writtenNow > 0) {
                return $writtenNow;
            }
        }
    }

    public function readExact(int $size): string {
        $data     = "";
        $dataSize = 0;
        while ($dataSize < $size) {
            $nextChunk = min($size - $dataSize, Stream::CHUNK_SIZE);
            $chunk     = $this->read($nextChunk);;
            $dataSize += strlen($chunk);
            $data     .= $chunk;
        }

        return $data;
    }

    public function read(int $size = 4096): string {
        while (true) {
            $this->loop->suspend(StreamWaker::read($this->stream));

            $error = null;
            set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
                $error = new \ErrorException(
                  $errstr,
                  0,
                  $errno,
                  $errfile,
                  $errline
                );
            });
            $data = stream_get_contents($this->stream, $size);
            restore_error_handler();

            if ($error !== null) {
                throw new \RuntimeException("Failed to read", previous: $error);
            }

            if ($data !== "" || $this->eof()) {
                return $data;
            }
        }
    }

    public static function fd(int $fd, string $mode = 'r+'): Stream {
        $fd = fopen("php://fd/" . $fd, $mode);
        if ($fd === false) {
            throw new \RuntimeException("Failed to open fd " . $fd);
        }

        return new Stream($fd);
    }

    public static function stdin(): Stream {
        return Stream::fd(0, 'r');
    }

    public static function stdout(): Stream {
        return Stream::fd(1, 'w');
    }

    public static function stderr(): Stream {
        return Stream::fd(2, 'w');
    }

    public function lines(): LineReader {
        return new LineReader($this);
    }
}