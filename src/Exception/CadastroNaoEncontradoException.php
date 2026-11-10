<?php

declare(strict_types = 1);

namespace ProvaSpider\Exception;

/***
 * NÃOHÁ cadastro no SEFAZ-BA Para o CNPJ consultado 
 */


final class CadastroNaoEncontradoException extends ConsultaException{
  
    private function __construct(public readonly string $cnpj){

       parent::__construct(sprintf('nenhum cadastro encontrado no SEFAZ-BA para o CNPJ %s', $cnpj));
    }

    public static function paraCnpj(string $cnpj):self{

        return new self($cnpj);
    }

}