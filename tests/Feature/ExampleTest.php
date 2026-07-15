<?php

test('redirects to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/login');
});