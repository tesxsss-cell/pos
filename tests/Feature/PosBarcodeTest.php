<?php
it('tidak menyediakan pendaftaran barcode kasir', function () { expect(\Illuminate\Support\Facades\Route::has('pos.barcode.store'))->toBeFalse(); });
