<?php

declare(strict_types=1);

use ProvaSpider\Exception\PortalIndisponivelException;
use ProvaSpider\Http\StreamHttpTransport;

it('converte falha de conexão em portal indisponível, guardando a causa', function () {
    // Porta local livre: nada escuta nela, então a conexão é recusada sem acessar a internet.
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $porta = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    try {
        (new StreamHttpTransport(timeoutSegundos: 2))->post("http://127.0.0.1:{$porta}/", ['CGC' => '1']);
        $this->fail('Era esperada PortalIndisponivelException.');
    } catch (PortalIndisponivelException $excecao) {
        expect($excecao->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($excecao->getMessage())->toContain('Não foi possível se comunicar');
    }
});