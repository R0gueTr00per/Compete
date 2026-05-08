<?php

it('root redirects to portal', function () {
    $response = $this->get('/');

    $response->assertRedirect('/portal');
});
