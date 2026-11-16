<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/bin', __DIR__.'/config', __DIR__.'/public', __DIR__.'/src', __DIR__.'/tests'])
    ->notPath(['bundles.php', 'reference.php'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'final_class' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
;
