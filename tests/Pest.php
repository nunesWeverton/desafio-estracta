<?php

declare(strict_types=1);

/** HTML de uma fixture sintética (dados fictícios) em tests/Fixtures. */
function fixture(string $nome): string
{
    return (string) file_get_contents(__DIR__ . '/Fixtures/' . $nome);
}

/** Transporte em memória: registra a requisição e devolve (ou lança) a resposta programada. */
final class FakeTransport implements ProvaSpider\Http\HttpTransport
{
    /** @var list<array{url: string, campos: array<string, string>}> */
    public array $requisicoes = [];

    public function __construct(private readonly ProvaSpider\Http\HttpResponse|Throwable $resposta)
    {
    }

    public function post(string $url, array $campos): ProvaSpider\Http\HttpResponse
    {
        $this->requisicoes[] = ['url' => $url, 'campos' => $campos];

        if ($this->resposta instanceof Throwable) {
            throw $this->resposta;
        }

        return $this->resposta;
    }
}