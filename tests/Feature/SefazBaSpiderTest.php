<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontradoException;
use ProvaSpider\Exception\FalhaHttpException;
use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Exception\RespostaInesperadaException;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\ValueObject\Consulta;

function consultarCom(HttpResponse|Throwable $resposta, string $cnpj = '00123456000149'): mixed
{
    return (new SefazBaSpider(new FakeTransport($resposta)))->consultar(new Consulta($cnpj));
}

it('envia o CNPJ por POST para result.asp com os campos do formulário', function () {
    $http = new FakeTransport(new HttpResponse(200, fixture('cadastro-completo.html')));

    (new SefazBaSpider($http))->consultar(new Consulta('00.123.456/0001-49'));

    expect($http->requisicoes)->toBe([[
        'url' => SefazBaSpider::URL_RESULTADO,
        'campos' => ['sefp' => '1', 'estado' => 'BA', 'CGC' => '00123456000149', 'B1' => 'CNPJ ->', 'CPF' => '', 'IE' => ''],
    ]]);
});

it('retorna o objeto de valor com os identificadores preservados', function () {
    $cadastro = consultarCom(new HttpResponse(200, fixture('cadastro-completo.html')));

    expect($cadastro->cnpj)->toBe('00123456000149')
        ->and($cadastro->inscricaoEstadual)->toBe('012345678')
        ->and($cadastro->razaoSocial)->toBe('EMPRESA FICTÍCIA DE TESTE LTDA');
});

it('informa o CNPJ sem cadastro, com status 200 ou 404', function (int $status) {
    try {
        consultarCom(new HttpResponse($status, fixture('sem-cadastro.html')));
        $this->fail('Era esperada CadastroNaoEncontradoException.');
    } catch (CadastroNaoEncontradoException $excecao) {
        expect($excecao->cnpj)->toBe('00123456000149');
    }
})->with([200, 404]);

it('propaga falha de comunicação com a causa original', function () {
    $falha = PortalIndisponivelException::porFalhaDeComunicacao(new RuntimeException('Connection timed out'));

    expect(fn () => consultarCom($falha))->toThrow(PortalIndisponivelException::class, 'Connection timed out');
});

it('trata 502/503/504 e aviso de manutenção como portal indisponível', function (HttpResponse $resposta) {
    expect(fn () => consultarCom($resposta))->toThrow(PortalIndisponivelException::class);
})->with([
    '503' => [new HttpResponse(503, 'Service Unavailable')],
    '504' => [new HttpResponse(504, '')],
    'página de manutenção' => [new HttpResponse(200, '<html><body>Sistema em manutenção</body></html>')],
]);

it('guarda o status das demais falhas HTTP', function (int $status) {
    try {
        consultarCom(new HttpResponse($status, '<html><body>Erro</body></html>'));
        $this->fail('Era esperada FalhaHttpException.');
    } catch (FalhaHttpException $excecao) {
        expect($excecao->statusCode)->toBe($status)
            ->and($excecao->url)->toBe(SefazBaSpider::URL_RESULTADO);
    }
})->with([404, 500]);

it('rejeita HTML inesperado', function () {
    expect(fn () => consultarCom(new HttpResponse(200, '<html><body><p>Página nova</p></body></html>')))
        ->toThrow(RespostaInesperadaException::class);
});