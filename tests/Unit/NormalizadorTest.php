<?php

declare(strict_types=1);

use ProvaSpider\Support\Normalizador;

describe('texto', function () {
    it('normaliza espaços e preserva acentos', function (?string $entrada, ?string $esperado) {
        expect(Normalizador::texto($entrada))->toBe($esperado);
    })->with([
        'pontas e espaços repetidos' => ["  EMPRESA   FICTÍCIA \n LTDA ", 'EMPRESA FICTÍCIA LTDA'],
        'nbsp do HTML' => ["RUA\u{00A0}DAS\u{00A0}\u{00A0}FLORES", 'RUA DAS FLORES'],
        'acentos e cedilha' => ['AÇÚCAR & CAFÉ', 'AÇÚCAR & CAFÉ'],
        'somente espaços vira null' => [" \t\u{00A0} ", null],
        'vazio vira null' => ['', null],
        'null continua null' => [null, null],
    ]);

    it('rejeita texto que não foi convertido para UTF-8', function () {
        $latin1 = mb_convert_encoding('FICTÍCIA', 'ISO-8859-1', 'UTF-8');

        expect(fn () => Normalizador::texto($latin1))->toThrow(InvalidArgumentException::class);
    });
});