<?php

namespace com\github\adangel\chunkphp;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase {

    public function testValidUser() {
        $this->assertFalse(Config::isValidUser('not-existing'));
        $this->assertFalse(Config::isValidUser('ünvalidCharacters'));
        $this->assertTrue(Config::isValidUser('jondoe'));
    }

    public function testValidUserAuthentication() {
        $this->assertFalse(Config::isValidUserAuthentication('invalid', 'user'));
        $this->assertFalse(Config::isValidUserAuthentication('jondoe', 'wrong password'));
        $this->assertTrue(Config::isValidUserAuthentication('jondoe', 'secret'));
    }
}
