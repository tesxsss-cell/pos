<?php

test('halaman awal mengarahkan pengguna ke dasbor', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
