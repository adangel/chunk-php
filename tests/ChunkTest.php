<?php

namespace com\github\adangel\chunkphp;

use PHPUnit\Framework\TestCase;

class ChunkTest extends TestCase {

    public function testBaseUrl() {
        $_SERVER['HTTP_HOST'] = 'chunk.example.com';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_FILENAME'] = 'index.php';

        $chunk = new Chunk('POST');
        $url = $chunk->determineBaseUrlFromRequest();
        $this->assertEquals("http://chunk.example.com", $url);
    }

    public function testBaseUrlFCGI() {
        $_SERVER['HTTP_HOST'] = 'chunk.example.com';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/joe/123';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/vhosts/hosting123/chunk.example.com/index.php';
        $_SERVER['REDIRECT_URL'] = '/joe/123';

        $chunk = new Chunk('GET');
        $url = $chunk->determineBaseUrlFromRequest();
        $this->assertEquals("https://chunk.example.com", $url);
        $this->assertEquals("/joe/123", $chunk->determinePathFromRequest());
    }

    /*
     * When starting the dev server as: php -S localhost:8000 index.php
     * and accessing http://localhost:8000/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html
     */
    public function testBaseUrlDev1() {
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html?chunk_php_verbose=1';
        $_SERVER['SCRIPT_NAME'] = '/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html';
        $_SERVER['SCRIPT_FILENAME'] = 'index.php';
        $_SERVER['PHP_SELF'] = '/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html';
        $_SERVER['QUERY_STRING'] = 'chunk_php_verbose=1';
        unset($_SERVER['PATH_INFO']);
        unset($_SERVER['HTTPS']);

        $chunk = new Chunk('GET');
        $this->assertEquals("http://localhost:8000", $chunk->determineBaseUrlFromRequest());
        $this->assertEquals("/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html", $chunk->determinePathFromRequest());
    }

    /*
     * When starting the dev server as: php -S localhost:8000
     * and accessing http://localhost:8000/index.php/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html
     */
    public function testBaseUrlDev2() {
        //$_SERVER['HTTPS'] = 'https';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['REQUEST_URI'] = '/index.php/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/home/user/chunk-php/index.php';
        $_SERVER['PHP_SELF'] = '/index.php/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html';
        $_SERVER['PATH_INFO'] = '/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html';
        unset($_SERVER['QUERY_STRING']);

        $chunk = new Chunk('GET');
        $this->assertEquals("http://localhost:8000/index.php", $chunk->determineBaseUrlFromRequest());
        $this->assertEquals("/jondoe/25033eba-9924-4686-bfdf-7915c636374b/index.html", $chunk->determinePathFromRequest());
    }

    public function testBaseUrlHttps() {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'chunk.example.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/home/user/chunk-php/index.php';
        $_SERVER['PHP_SELF'] = '/index.php';
        unset($_SERVER['PATH_INFO']);
        unset($_SERVER['QUERY_STRING']);

        $chunk = new Chunk('GET');
        $this->assertEquals("https://chunk.example.org", $chunk->determineBaseUrlFromRequest());
        $this->assertEquals("/", $chunk->determinePathFromRequest());
    }
}
