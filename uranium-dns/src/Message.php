<?php

namespace Cijber\Uranium\Dns;

/**
 * @property QuestionRecord[] $requestRecords
 * @property ResourceRecord[] $responseRecords
 * @property ResourceRecord[] $nameserverRecords
 * @property ResourceRecord[] $additionalRecords
 */
class Message {
    const OP_QUERY  = 0;
    const OP_IQUERY = 1;
    const OP_STATUS = 2;

    const R_OK              = 0;
    const R_FORMAT_ERROR    = 1;
    const R_SERVER_FAILURE  = 2;
    const R_NAME_ERROR      = 3;
    const R_NXDOMAIN        = 3;
    const R_NOT_IMPLEMENTED = 4;
    const R_REFUSED         = 5;

    public function __construct(
      private int $id,
      private bool $response,
      private int $operation,
      public  array $requestRecords = [],
      public array $responseRecords = [],
      public  array $authoritativeRecords = [],
      public  array $additionalRecords = [],
      private bool $authoritativeAnswer = false,
      private bool $truncated = false,
      private bool $recursionDesired = false,
      private bool $recursionAvailable = false,
      private int $responseCode = 0,
    ) {
    }

    public static function nxdomain(Message $message): Message {
        return new Message($message->getId(), true, 0, $message->getRequestRecords(), recursionDesired: $message->isRecursionDesired(), responseCode: Message::R_NXDOMAIN);
    }

    public static function question(array $labels, int $type, int $class = ResourceClass::IN, bool $recursion = false): Message {
        return new Message(0, false, Message::OP_QUERY, requestRecords: [new QuestionRecord($labels, $type, $class)], recursionDesired: $recursion);
    }

    public static function formatError(Message $message): Message {
        return new Message($message->getId(), true, 0, $message->getRequestRecords(), recursionDesired: $message->isRecursionDesired(), responseCode: Message::R_FORMAT_ERROR);
    }

    public function getId(): int {
        return $this->id;
    }

    public static function empty(Message $message) {
        return new Message($message->getId(), true, 0, $message->getRequestRecords(), recursionDesired: $message->isRecursionDesired(), responseCode: Message::R_OK);
    }

    /**
     * @return ResourceRecord[]
     */
    public function getAdditionalRecords(): array {
        return $this->additionalRecords;
    }

    /**
     * @return ResourceRecord[]
     */
    public function getAuthoritativeRecords(): array {
        return $this->authoritativeRecords;
    }

    public function getOperation(): int {
        return $this->operation;
    }

    /**
     * @return QuestionRecord[]
     */
    public function getRequestRecords(): array {
        return $this->requestRecords;
    }

    public function getQuestion(): ?QuestionRecord {
        return $this->requestRecords[0];
    }

    public function getResponseCode(): int {
        return $this->responseCode;
    }

    /**
     * @return ResourceRecord[]
     */
    public function getResponseRecords(): array {
        return $this->responseRecords;
    }

    public function setResponseRecords(array $responseRecords): void {
        $this->responseRecords = $responseRecords;
    }

    public function setAuthoritativeRecords(array $authoritativeRecords): void {
        $this->authoritativeRecords = $authoritativeRecords;
    }

    public function setAdditionalRecords(array $additionalRecords): void
    {
        $this->additionalRecords = $additionalRecords;
    }

    public function toBytes(?int $size = null): string {
        $data = chr($this->id >> 8) . chr($this->id & 255);
        $bf   = 0;
        $bf   |= ($this->response ? 128 : 0);
        $bf   |= ($this->operation << 3);
        $bf   |= ($this->authoritativeAnswer ? 4 : 0);
        $bf   |= ($this->truncated ? 2 : 0);
        $bf   |= ($this->recursionDesired ? 1 : 0);
        $data .= chr($bf);
        $bf   = 0;
        $bf   |= ($this->recursionAvailable ? 128 : 0);
        $bf   |= $this->responseCode & (1 + 2 + 4 + 8);
        $data .= chr($bf);
        $qc   = count($this->requestRecords);
        $data .= chr(($qc >> 8) & 255) . chr($qc & 255);
        $rc   = count($this->responseRecords);
        $data .= chr(($rc >> 8) & 255) . chr($rc & 255);
        $nc   = count($this->authoritativeRecords);
        $data .= chr(($nc >> 8) & 255) . chr($nc & 255);
        $ac   = count($this->additionalRecords);
        $data .= chr(($ac >> 8) & 255) . chr($ac & 255);

        $rrs = array_merge($this->requestRecords, $this->responseRecords, $this->authoritativeRecords, $this->additionalRecords);

        foreach ($rrs as $rr) {
            $oldLength = strlen($data);
            $rr->toBytes($data);

            if ($size !== null && strlen($data) > $size) {
                $data = substr($data, 0, $oldLength);

                // Set truncated flag
                $data[2] = chr(ord($data[2]) | 2);
                break;
            }
        }

        return $data;
    }

    public function isResponse(): bool {
        return $this->response;
    }

    public function isAuthoritativeAnswer(): bool {
        return $this->authoritativeAnswer;
    }

    public function isTruncated(): bool {
        return $this->truncated;
    }

    public function isRecursionDesired(): bool {
        return $this->recursionDesired;
    }

    public function setRecursionDesired(bool $recursionDesired): void {
        $this->recursionDesired = $recursionDesired;
    }

    public function isRecursionAvailable(): bool {
        return $this->recursionAvailable;
    }

    public function addRequestRecord(QuestionRecord $record) {
        $this->requestRecords[] = $record;
    }

    public function addResponseRecord(ResourceRecord $record) {
        $this->responseRecords[] = $record;
    }

    public function setId(int $id) {
        $this->id = $id;
    }

    public function __clone(): void {
        $this->requestRecords    = array_values($this->requestRecords);
        $this->responseRecords   = array_values($this->responseRecords);
        $this->authoritativeRecords = array_values($this->authoritativeRecords);
        $this->additionalRecords = array_values($this->additionalRecords);

        foreach ($this->requestRecords as $i => $record) {
            $this->requestRecords[$i] = clone $record;
        }

        foreach ($this->responseRecords as $i => $record) {
            $this->responseRecords[$i] = clone $record;
        }

        foreach ($this->authoritativeRecords as $i => $record) {
            $this->authoritativeRecords[$i] = clone $record;
        }

        foreach ($this->additionalRecords as $i => $record) {
            $this->additionalRecords[$i] = clone $record;
        }
    }

    /**
     * @param bool $authoritativeAnswer
     */
    public function setAuthoritativeAnswer(bool $authoritativeAnswer): void
    {
        $this->authoritativeAnswer = $authoritativeAnswer;
    }



    public function isNotFound() {
        return $this->responseCode === self::R_NXDOMAIN && count($this->responseRecords) === 0 && count($this->authoritativeRecords) === 1 && $this->authoritativeRecords[0]->type === ResourceType::SOA;
    }
}