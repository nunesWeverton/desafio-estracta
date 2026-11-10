<?php

// Servidor de teste (php -S) que imita o portal: a página de aviso só existe
// para quem tem a sessão criada ao abrir o formulário.

$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$temSessao = ($_COOKIE['ASPSESSIONID'] ?? '') === 'abc123';

if ($caminho === '/consultaBa.asp') {
    setcookie('ASPSESSIONID', 'abc123');
    echo '<form method="post" action="result.asp"></form>';
} elseif ($caminho === '/result.asp') {
    header('Location: aviso.asp', true, 302);
} elseif ($caminho === '/aviso.asp' && $temSessao) {
    echo '<b>Cnpj incorreto</b> - dígito verificador !';
} else {
    http_response_code(404);
    echo '404 - File or directory not found.';
}