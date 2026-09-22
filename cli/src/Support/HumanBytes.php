<?php

namespace FileServerCli\Support;

final class HumanBytes
{
    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === end($units)) {
                return sprintf('%.2f %s', $value, $unit);
            }
            $value /= 1024;
        }

        return "{$bytes} B";
    }

    private function __construct() {}
}
