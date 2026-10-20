<?php
it('hanya dua peran pada edisi online', function () { expect(array_keys(\App\Enums\UserRole::options()))->toBe(['pengelola','kasir']); });
