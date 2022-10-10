<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;

return static function (Rector\Config\RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__.'/app/bundles', __DIR__.'/plugins']);
    $rectorConfig->skip(
        [
            __DIR__.'/*/test/*',
            __DIR__.'/*/tests/*',
            __DIR__.'/*/Test/*',
            __DIR__.'/*/Tests/*',
            __DIR__.'/*.html.php',
            __DIR__.'/*.less.php',
            __DIR__.'/*.inc.php',
            __DIR__.'/*.js.php',
        ]
    );

    $rectorConfig->parallel();

    $rectorConfig->symfonyContainerXml(__DIR__.'/var/cache/test/appAppKernelTestDebugContainer.xml');

    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory(__DIR__.'/var/cache/rector');

    // Define what rule sets will be applied
    $rectorConfig->sets([
        \Rector\Symfony\Set\SymfonyLevelSetList::UP_TO_SYMFONY_41,

        // @todo implement the whole set. Start rule by rule below.
        // \Rector\Set\ValueObject\SetList::DEAD_CODE
    ]);

    // Define what single rules will be applied
    $rectorConfig->rule(\Rector\DeadCode\Rector\BooleanAnd\RemoveAndTrueRector::class);
    $rectorConfig->rule(\Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector::class);
    $rectorConfig->rule(\Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector::class);
    $rectorConfig->rule(\Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector::class);
};
