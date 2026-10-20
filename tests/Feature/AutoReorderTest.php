<?php
it('tidak menyediakan pemesanan restock otomatis', function () { expect(\Illuminate\Support\Facades\Route::has('reorder.acknowledge'))->toBeFalse(); });
