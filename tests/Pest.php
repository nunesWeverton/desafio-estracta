<?php

declare(strict_types=1);

/** HTML de uma fixture sintética (dados fictícios) em tests/Fixtures. */
function fixture(string $nome): string
{
    return (string) file_get_contents(__DIR__ . '/Fixtures/' . $nome);
}

/** Transporte em memória: registra a requisição e devolve (ou lança) a resposta programada. */
/** Transporte em memória: registra as requisições e devolve (ou lança) as respostas programadas, em ordem. */
final class FakeTransport implements ProvaSpider\Http\HttpTransport
{
    /** @var list<array{metodo: string, url: string, campos: array<string, string>}> */
    public array $requisicoes = [];

    /** @var list<ProvaSpider\Http\HttpResponse|Throwable> */
    private array $respostas;

    public function __construct(ProvaSpider\Http\HttpResponse|Throwable ...$respostas)
    {
        $this->respostas = array_values($respostas);
    }

    public function get(string $url): ProvaSpider\Http\HttpResponse
    {
        return $this->responder('GET', $url, []);
    }

    public function post(string $url, array $campos): ProvaSpider\Http\HttpResponse
    {
        return $this->responder('POST', $url, $campos);
    }

    /** @param array<string, string> $campos */
    private function responder(string $metodo, string $url, array $campos): ProvaSpider\Http\HttpResponse
    {
        $this->requisicoes[] = ['metodo' => $metodo, 'url' => $url, 'campos' => $campos];
        $resposta = array_shift($this->respostas) ?? throw new LogicException("Requisição inesperada: {$metodo} {$url}");

        if ($resposta instanceof Throwable) {
            throw $resposta;
        }

        return $resposta;
    }
}