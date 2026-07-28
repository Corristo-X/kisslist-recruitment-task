<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/migrations', __DIR__.'/public'])
    ->notPath('reference.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
