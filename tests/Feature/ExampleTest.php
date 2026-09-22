<?php

test('the application redirects the home page to the app panel', function () {
    $response = $this->get('/');

    $response->assertRedirect('/app');
});
