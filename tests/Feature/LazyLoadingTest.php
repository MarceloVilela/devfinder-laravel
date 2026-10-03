<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

it('proíbe lazy loading fora de produção', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue();
});
