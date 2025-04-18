<?php
return function($method, $params, $body) {
    if ($method === 'GET') {
        return ['message' => 'User endpoint accessed'];
    }
    return ['error' => 'Unsupported method'];
};
