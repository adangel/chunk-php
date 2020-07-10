<?php

namespace com\github\adangel\chunkphp;

use Exception;

class User {
    private $name;

    function __construct($name) {
        if (preg_match("/^[a-zA-Z0-9_-]+$/", $name) !== 1) {
            throw new Exception("Invalid username!");
        }
        $this->name = $name;
    }

    public function __toString() {
        return $this->name;
    }
}
