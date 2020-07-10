<?php

namespace com\github\adangel\chunkphp;

class Config {

    private static $USERS = array(
        "jondoe" => "secret",
    );

    public static function getDataDir() {
        return __DIR__ . '/../data/';
    }

    public static function getCleanupThreshold() {
        $days = 90;
        return $days * 24 * 3600;
    }

    public static function getMaxFileSize() {
        return 10 * 1024 * 1024; #10m
    }

    public static function isValidUser($user) {
        if (array_key_exists($user, Config::$USERS) && preg_match("/^[a-zA-Z0-9_-]+$/", $user) == 1) {
            return TRUE;
        }
        return FALSE;
    }

    public static function isValidUserAuthentication($user, $pw) {
        if (array_key_exists($user, Config::$USERS) && Config::$USERS[$user] === $pw) {
            return TRUE;
        }
        return FALSE;
    }
}
