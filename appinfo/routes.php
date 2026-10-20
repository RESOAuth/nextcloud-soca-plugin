<?php
return ['routes' => [
    ['name' => 'login#metadata', 'url' => '/metadata', 'verb' => 'GET'],
    ['name' => 'login#jwks', 'url' => '/jwks', 'verb' => 'GET'],
    ['name' => 'login#start', 'url' => '/login', 'verb' => 'GET'],
    ['name' => 'login#callback', 'url' => '/callback', 'verb' => 'GET'],
    ['name' => 'login#check', 'url' => '/check', 'verb' => 'POST'],
    ['name' => 'login#test', 'url' => '/test', 'verb' => 'POST'],
]];
