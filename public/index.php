<?php

declare(strict_types=1);

/*
 * Front controller da aplicação.
 *
 * O document root aponta para public/, portanto este é o único arquivo PHP
 * acessível diretamente pelo servidor web. O despacho das rotas de routes/
 * será feito a partir daqui.
 */

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Obsyrium Store</title>
</head>
<body>
	<h1>Obsyrium Store</h1>
	<p>Aplicação em construção.</p>
</body>
</html>
