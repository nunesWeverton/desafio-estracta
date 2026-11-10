<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

use Throwable;

/**
 * Não foi possível obter uma resposta útil do portal: timeout, falha de rede ou manutenção.
 */
final class PortalIndisponivelException extends ConsultaException
{
    public static function porFalhaDeComunicacao(Throwable $causa): self
    {
        return new self(
            sprintf('Não foi possível se comunicar com o portal da SEFAZ-BA: %s', $causa->getMessage()),
            previous: $causa,
        );
    }

    public static function emManutencao(): self
    {
        return new self('O portal da SEFAZ-BA informou que está temporariamente fora do ar.');
    }
}