<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        // Preserve the controller method layout documented in AGENTS.md.
        'braces_position' => false,
        'method_argument_space' => [
            'after_heredoc' => true,
            'on_multiline' => 'ensure_fully_multiline',
            'attribute_placement' => 'standalone',
        ],
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => ['array_destructuring', 'arrays', 'match'],
        ],
    ])
    ->setFinder($finder)
;
