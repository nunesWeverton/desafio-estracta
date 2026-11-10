<?php
declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal respondeu com um status HTTP de erro (4xx ou 5xx).
 */

final class FalhaHttpException extends ConsultaException{

    private function __construct(public readonly int $statusCode,
    public readonly string $url){
      parent::__construct(sprintf('o portal respondeu com status HTTP %d em %s', $statusCode, $url));
    }
     

    public static function comStatus( int $statusCode, string $url): self{
        return new self($statusCode, $url);
    }
   
}