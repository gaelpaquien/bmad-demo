<?php

it('renders the themes page', function () {
    test()->get('/configuration/themes')->assertInertia(fn ($page) => $page
        ->component('Documents/Themes')
    );
});

it('redirects the bare configuration URL to the tags page', function () {
    test()->get('/configuration')->assertRedirect('/configuration/tags');
});
