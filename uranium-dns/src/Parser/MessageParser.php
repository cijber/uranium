<?php

namespace Cijber\Uranium\Dns\Parser;

use Cijber\Uranium\Dns\Message;


class MessageParser {
    public static function parse(string $data, int &$offset = 0): Message {
        $id       = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $bf       = ord($data[$offset++]);
        $response = ($bf & 128) > 0;
        $opcode   = ($bf & (64 | 32 | 16 | 8)) >> 3;
        $aa       = ($bf & 4) > 0;
        $tc       = ($bf & 2) > 0;
        $rd       = ($bf & 1) > 0;
        $bf       = ord($data[$offset++]);
        $ra       = ($bf & 128) > 0;
        $rcode    = ($bf & (1 | 2 | 4 | 8));
        $qdcount  = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $ancount  = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $nscount  = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $arcount  = (ord($data[$offset++]) << 8) + ord($data[$offset++]);

        $questions = [];
        for ($i = 0; $i < $qdcount; $i++) {
            $questions[] = RecordParser::parseQuestion($data, $offset);
        }

        $answers = [];
        for ($i = 0; $i < $ancount; $i++) {
            $answers[] = RecordParser::parseResource($data, $offset);
        }

        $nameservers = [];
        for ($i = 0; $i < $nscount; $i++) {
            $nameservers[] = RecordParser::parseResource($data, $offset);
        }

        $additional = [];
        for ($i = 0; $i < $arcount; $i++) {
            $additional[] = RecordParser::parseResource($data, $offset);
        }

        return new Message($id, $response, $opcode, $questions, $answers, $nameservers, $additional, $aa, $tc, $rd, $ra, $rcode);
    }
}