<?php

declare(strict_types=1);

namespace ProvaSpider\Support;

use InvalidArgumentException;

/**
 * Limpa os valores extraídos do HTML antes de irem para o objeto de valor.
 * Usado apenas para dados vindos do portal; a validação da entrada fica em Consulta.
 * Espera texto já convertido para UTF-8: a conversão é responsabilidade de quem lê a página.
 */
final class Normalizador
{
    /**
     * Converte &nbsp; em espaço, junta espaços repetidos e apara as pontas.
     * Texto que fica vazio vira null, porque null representa campo ausente.
     */
    public static function texto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (!mb_check_encoding($valor, 'UTF-8')) {
            throw new InvalidArgumentException('O texto a normalizar deve estar em UTF-8.');
        }

        // \x{00A0} é o &nbsp;; o \s comum não o reconhece.
        $limpo = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $valor));

        return $limpo === '' ? null : $limpo;
    }
    public static function somenteDigitos(?string $valor): ?string{

        $texto = self::texto($valor);

        if($texto === null){
            return null;
        }
        $digitos = (string) preg_replace('/\D+/', '', $texto);

        return $digitos === '' ? null : $digitos;
    }
}