<?php

require __DIR__ . '/src/Chunk.php';
require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/File.php';
require __DIR__ . '/src/MimeTypes.php';
require __DIR__ . '/src/Storage.php';
require __DIR__ . '/src/User.php';
require __DIR__ . '/src/Uuid.php';

use com\github\adangel\chunkphp\Chunk;

$chunk = new Chunk($_SERVER['REQUEST_METHOD']);
$chunk->handleRequest();
