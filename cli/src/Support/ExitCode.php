<?php

namespace FileServerCli\Support;

final class ExitCode
{
    public const int SUCCESS = 0;
    public const int GENERIC_ERROR = 1;
    public const int USAGE_ERROR = 2;
    public const int AUTH_ERROR = 3;
    public const int NOT_FOUND = 4;
    public const int NETWORK_ERROR = 5;

    private function __construct() {}
}
