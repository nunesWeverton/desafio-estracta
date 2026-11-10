<?php

declare(strict_types=1);

/** HTML de uma fixture sintética (dados fictícios) em tests/Fixtures. */
function fixture(string $nome): string
{
    return (string) file_get_contents(__DIR__ . '/Fixtures/' . $nome);
}