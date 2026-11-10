<?php

declare(strict_types=1);

use ProvaSpider\Parser\CadastroHtmlParser;

it('extrai e mapeia os campos do cadastro, com acentos e zeros à esquerda', function () {
    $cadastro = (new CadastroHtmlParser())->parse(fixture('cadastro-completo.html'));

    expect($cadastro?->jsonSerialize())->toBe([
        'inscricaoEstadual' => '012345678',
        'cnpj' => '00123456000149',
        'razaoSocial' => 'EMPRESA FICTÍCIA DE TESTE LTDA',
        'nomeFantasia' => 'CAFÉ EXEMPLO',
        'situacaoCadastral' => 'ATIVO',
        'endereco' => 'RUA DAS ACÁCIAS, 000120, SALA 01 - CENTRO - SALVADOR/BA - CEP 40000-000',
        'atividadeEconomica' => '0000001 - Comércio varejista de produtos fictícios',
        'regimeApuracao' => 'C/CORRENTE FISCAL',
    ]);
});

it('usa o endereço do estabelecimento, e não o de correspondência ou o do contador', function () {
    $endereco = (new CadastroHtmlParser())->parse(fixture('cadastro-completo.html'))?->endereco;

    expect($endereco)->not->toContain('CONTADOR')->not->toContain('CAIXA POSTAL');
});

it('representa campos ausentes como null sem usar valores de outros blocos', function () {
    $cadastro = (new CadastroHtmlParser())->parse(fixture('cadastro-campos-ausentes.html'));

    expect($cadastro?->nomeFantasia)->toBeNull()
        ->and($cadastro?->atividadeEconomica)->toBeNull()
        ->and($cadastro?->regimeApuracao)->toBeNull()
        ->and($cadastro?->endereco)->toBe('TRAVESSA SÃO JOSÉ, S/N - VITÓRIA DA CONQUISTA/BA - CEP 45000-000')
        ->and($cadastro?->inscricaoEstadual)->toBe('001002003');
});

it('converte página em Windows-1252 para UTF-8', function () {
    $html = mb_convert_encoding(
        '<table><th><b>Dados da empresa</b></th><tr bgcolor="#e2ecf5"><td><b>Identificação</b></td></tr>'
        . '<tr><td><b>CNPJ:</b> 00.123.456/0001-49</td></tr><tr><td><b>Razão Social:</b> AÇÚCAR & CAFÉ LTDA</td></tr></table>',
        'Windows-1252',
        'UTF-8',
    );

    expect((new CadastroHtmlParser())->parse($html)?->razaoSocial)->toBe('AÇÚCAR & CAFÉ LTDA');
});

it('devolve null quando a página não traz um cadastro', function (string $html) {
    expect((new CadastroHtmlParser())->parse($html))->toBeNull();
})->with([
    'sem cadastro' => [fn () => fixture('sem-cadastro.html')],
    'manutenção' => ['<html><body><h1>Sistema em manutenção</h1></body></html>'],
    'corpo vazio' => [''],
]);

it('reconhece o aviso de CNPJ sem cadastro', function () {
    $parser = new CadastroHtmlParser();

    expect($parser->indicaSemCadastro(fixture('sem-cadastro.html')))->toBeTrue()
        ->and($parser->indicaSemCadastro(fixture('cadastro-completo.html')))->toBeFalse();
});

it('reconhece o aviso de manutenção', function () {
    $parser = new CadastroHtmlParser();

    expect($parser->indicaManutencao('<p>Sistema temporariamente fora do ar.</p>'))->toBeTrue()
        ->and($parser->indicaManutencao(fixture('cadastro-completo.html')))->toBeFalse();
});