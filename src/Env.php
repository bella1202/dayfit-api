<?php
class Env {
    public static function load($path) {
        foreach (file($path) as $line) {
            if (trim($line) === '' || strpos($line, '#') === 0) continue;
            putenv(trim($line));
        }
    }

    public static function get($key, $default = null) {
        return getenv($key) ?: $default;
    }
}
