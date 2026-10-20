<?php
it('menjaga folder tampilan lama tanpa route cabang', function () { expect(\Illuminate\Support\Facades\Route::has('branches.index'))->toBeFalse(); });
