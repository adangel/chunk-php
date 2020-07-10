<?php

namespace com\github\adangel\chunkphp;

use Exception;

class Uuid {
    private $id;

    function __construct($id = NULL) {
        if (isset($id)) {
            $this->id = $id;
        } else {
            $this->id = Uuid::guidv4();
        }

        if (preg_match("/^[a-f0-9-]{36}$/", $this->id) !== 1) {
            throw new Exception('Invalid uuid!');
        }
    }

    function __toString() {
        return $this->id;
    }

    # https://stackoverflow.com/a/15875555/1169968
    private static function guidv4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
