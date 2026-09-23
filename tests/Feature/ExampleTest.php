<?php

test('the application serves the landing page at the root', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});
