<?php

declare(strict_types=1);

it('mostra o tipo da exceção e encerra com código 1', function () {
    $processo = proc_open(
        [PHP_BINARY, dirname(__DIR__, 2) . '/bin/consultar.php', 'abc'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    $saida = stream_get_contents($pipes[1]);
    $erro = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($processo))->toBe(1)
        ->and($saida)->toBe('')
        ->and($erro)->toStartWith('InvalidArgumentException: ');
});