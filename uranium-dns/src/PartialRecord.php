<?php

namespace Cijber\Uranium\Dns;

abstract class PartialRecord
{
    public ?string $labelString = null;

    public function __construct(
        public array $labels,
        public int   $type,
        public int   $class,
    )
    {
    }

    public function getLabelString(): string
    {
        if ($this->labelString === null) {
            $this->labelString = implode(".", $this->labels) . ".";
        }

        return $this->labelString;
    }

    public function setLabels(array $labels): void
    {
        $this->labelString = null;
        $this->labels = $labels;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getClass(): int
    {
        return $this->class;
    }

    public function getType(): int
    {
        return $this->type;
    }

    protected static function writeUncompressedLabels(array $labels): string
    {
        $data = "";
        foreach ($labels as $label) {
            $data .= chr(strlen($label));
            $data .= $label;
        }

        $data .= "\x00";
        return $data;
    }

    protected function writeLabels(string &$target = "", ?string $source = null, ?array $labels = null): string
    {
        if ($source === null) {
            $source = $target;
        }

        $labels ??= $this->labels;
        $binaryLabels = [];
        foreach ($labels as $label) {
            $binaryLabels[] = chr(strlen($label)) . $label;
        }

        for ($i = 0; $i < count($binaryLabels); $i++) {
            $data = implode("", array_slice($binaryLabels, $i));
            if (($ptr = strpos($source, $data)) !== false) {
                $target .= chr((($ptr << 8) & 255) | 192);
                $target .= chr($ptr & 255);

                return $target;
            }

            $target .= $binaryLabels[$i];
        }

        $target .= "\x00";

        return $target;
    }

    abstract function toBytes(string &$target = ""): string;
}