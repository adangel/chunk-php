<?php

require __DIR__ . '/src/Chunk.php';
require __DIR__ . '/src/Config.php';

use com\github\adangel\chunkphp\Chunk;

$chunk = new Chunk($_SERVER['REQUEST_METHOD']);
$chunk->handleRequest();
