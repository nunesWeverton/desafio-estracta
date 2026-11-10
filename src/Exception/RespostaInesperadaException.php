<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal respondeu, mas o conteúdo não tem a estrutura esperada (por exemplo, o layout mudou).
 */
final class RespostaInesperadaException extends ConsultaException
{
    public static function estruturaNaoReconhecida(string $detalhe): self
    {
        return new self(sprintf('A resposta do portal não tem a estrutura esperada: %s', $detalhe));
    }
}