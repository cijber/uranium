<?php

namespace Cijber\Uranium\IO;

use Cijber\Uranium\IO\Utils\LineReader;
use Cijber\Uranium\Loop;
use JetBrains\PhpStorm\Pure;
use RuntimeException;


abstract class Stream
{
    const CHUNK_SIZE = 1_000_000;

    protected Loop $loop;

    public function slurp(): string
    {
        $data = "";
        while ( ! $this->eof()) {
            $data .= $this->read();
        }

        return $data;
    }

    abstract public function waitReadable(): bool;

    abstract public function waitWritable(): bool;

    abstract public function eof(): bool;

    abstract public function read(int $size = 4096): string;

    abstract public function write(string $data, ?int $size = 0): int;

    abstract public function close();

    abstract public function flush();

    #[Pure]
    public function lines(): LineReader
    {
        return new LineReader($this);
    }

    public function writeAll(string $data)
    {
        $toWrite = strlen($data);

        while ($toWrite > 0) {
            $nextSize = min(Stream::CHUNK_SIZE, $toWrite);
            $chunk    = $this->write($data, $nextSize);
            $toWrite  -= $chunk;
            $data     = substr($data, $chunk);
        }
    }

    public function readExact(int $size): string
    {
        $data     = "";
        $dataSize = 0;
        while ($dataSize < $size) {
            $nextChunk = min($size - $dataSize, Stream::CHUNK_SIZE);
            $chunk     = $this->read($nextChunk);
            $dataSize  += strlen($chunk);
            $data      .= $chunk;
        }

        return $data;
    }

    public static function fd(int $stream, string $mode = 'r+'): Stream
    {
        $stream = fopen("php://fd/" . $stream, $mode);
        if ($stream === false) {
            throw new RuntimeException("Failed to open fd " . $stream);
        }

        return new PhpStream($stream);
    }

    public static function stdin(): Stream
    {
        return Stream::fd(0, 'r');
    }

    public static function stdout(): Stream
    {
        return Stream::fd(1, 'w');
    }

    public static function stderr(): Stream
    {
        return Stream::fd(2, 'w');
    }

    public function __destruct()
    {
        $this->close();
    }
}