<?php

namespace com\github\adangel\chunkphp;

use PHPUnit\Framework\TestCase;

class ChunkTest extends TestCase {

    public function testA() {
        $this->assertEquals('a', 'a');
    }

    public function testGetTargetUrl() {
        $_SERVER['HTTP_HOST'] = 'chunk.example.com';
        $_SERVER['REQUEST_URI'] = '/';
        $uuid = Chunk::guidv4();

        $chunk = new Chunk('POST');
        $chunk->init_for_tests('jondoe', $uuid);

        $url = $chunk->getTargetUrl();
        $this->assertEquals("http://chunk.example.com/jondoe/$uuid", $url);
    }

    public function testGetTargetUrlVerbose() {
        $_GET['chunk_php_verbose'] = 1;
        $_SERVER['HTTP_HOST'] = 'chunk.example.com';
        $_SERVER['REQUEST_URI'] = '/?chunk_php_verbose';
        $_SERVER['QUERY_STRING'] = 'chunk_php_verbose';
        $uuid = Chunk::guidv4();

        $chunk = new Chunk('POST');
        $chunk->init_for_tests('jondoe', $uuid);

        $url = $chunk->getTargetUrl();
        $this->assertEquals("http://chunk.example.com/jondoe/$uuid?chunk_php_verbose", $url);
    }
}
