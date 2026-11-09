<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\ConsultaException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Exception\RespostaInesperadaException;

it('mantém todas as falhas da consulta sob ConsultaException, como exige o contrato do Spider', function (ConsultaException $excecao) {
    expect($excecao)->toBeInstanceOf(ConsultaException::class);
})->with([
    'cadastro não encontrado' => fn () => CadastroNaoEncontradoException::paraCnpj('00000000000191'),
    'falha HTTP' => fn () => FalhaHttpException::comStatus(500, 'https://exemplo.test'),
    'portal indisponível' => fn () => PortalIndisponivelException::emManutencao(),
    'resposta inesperada' => fn () => RespostaInesperadaException::estruturaNaoReconhecida('tabela ausente'),
]);

it('informa qual CNPJ não foi encontrado', function () {
    $excecao = CadastroNaoEncontradoException::paraCnpj('00000000000191');

    expect($excecao->cnpj)->toBe('00000000000191')
        ->and($excecao->getMessage())->toContain('00000000000191');
});

it('guarda status e URL da falha HTTP', function () {
    $excecao = FalhaHttpException::comStatus(503, 'https://exemplo.test/consulta');

    expect($excecao->statusCode)->toBe(503)
        ->and($excecao->url)->toBe('https://exemplo.test/consulta')
        ->and($excecao->getMessage())->toContain('503');
});

it('preserva a causa original quando a comunicação falha', function () {
    $causa = new RuntimeException('Connection timed out');

    $excecao = PortalIndisponivelException::porFalhaDeComunicacao($causa);

    expect($excecao->getPrevious())->toBe($causa)
        ->and($excecao->getMessage())->toContain('Connection timed out');
});

it('descreve o que não foi reconhecido na resposta', function () {
    $excecao = RespostaInesperadaException::estruturaNaoReconhecida('tabela de dados cadastrais ausente');

    expect($excecao->getMessage())->toContain('tabela de dados cadastrais ausente');
});