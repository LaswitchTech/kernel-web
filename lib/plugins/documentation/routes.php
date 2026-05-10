<?php

$router->get('/docs/{path*}', 'Controllers\Admin\DocsController@index', ['WebAuth', 'WebPermission:docs.read'], 1);
