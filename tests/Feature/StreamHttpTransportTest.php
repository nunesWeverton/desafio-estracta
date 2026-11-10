<?php

declare(strict_types=1);

use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Http\StreamHttpTransport;

function portaLivre(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $porta = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    return $porta;
}

it('converte falha de conexão em portal indisponível, guardando a causa', function () {
    // Nada escuta na porta livre, então a conexão é recusada sem acessar a internet.
    $porta = portaLivre();

    try {
        (new StreamHttpTransport(timeoutSegundos: 2))->post("http://127.0.0.1:{$porta}/", ['CGC' => '1']);
        $this->fail('Era esperada PortalIndisponivelException.');
    } catch (PortalIndisponivelException $excecao) {
        expect($excecao->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($excecao->getMessage())->toContain('Não foi possível se comunicar');
    }
});

it('mantém a sessão do formulário no POST e no redirecionamento', function () {
    $roteador = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'servidor-sessao.php';
    expect(is_file($roteador))->toBeTrue("Servidor de teste não encontrado em {$roteador}");

    $porta = portaLivre();
    $nulo = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $servidor = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$porta}", $roteador],
        [1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']],
        $pipes,
    );

    try {
        // Espera o servidor aceitar conexões (até 5 s); as tentativas recusadas são esperadas.
        set_error_handler(static fn (): bool => true);
        for ($i = 0; $i < 50; $i++) {
            $conexao = stream_socket_client("tcp://127.0.0.1:{$porta}", $codigo, $mensagem, 0.1);
            if ($conexao !== false) {
                fclose($conexao);
                break;
            }
            usleep(100_000);
        }
        restore_error_handler();
        $base = "http://127.0.0.1:{$porta}";

        // Sem abrir o formulário antes: sem sessão, o aviso dá 404.
        $semSessao = (new StreamHttpTransport(timeoutSegundos: 5))->post("{$base}/result.asp", ['CGC' => '1']);

        // Abrindo antes, o cookie segue no POST e no redirecionamento para o aviso.
        $http = new StreamHttpTransport(timeoutSegundos: 5);
        $http->get("{$base}/consultaBa.asp");
        $comSessao = $http->post("{$base}/result.asp", ['CGC' => '1']);
    } finally {
        proc_terminate($servidor);
        proc_close($servidor);
    }

    expect($semSessao->statusCode)->toBe(404)
        ->and($comSessao->statusCode)->toBe(200)
        ->and($comSessao->body)->toContain('Cnpj incorreto');
});