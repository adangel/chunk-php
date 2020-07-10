<?php

namespace com\github\adangel\chunkphp;

use PHPUnit\Framework\TestCase;

class ChunkTest extends TestCase {

    public function testBaseUrl() {
        $_SERVER['HTTP_HOST'] = 'chunk.example.com';
        $_SERVER['REQUEST_URI'] = '/';

        $chunk = new Chunk('POST');

        $url = $chunk->determineBaseUrlFromRequest();
        $this->assertEquals("http://chunk.example.com", $url);
    }
}
