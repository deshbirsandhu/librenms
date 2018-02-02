<?php
require_once 'vendor/autoload.php';

$github_token_file =  getenv('HOME') . "/.github-api-token";
if (is_file($github_token_file)) {
    $token = trim(file_get_contents($github_token_file));
} else {
    echo "Warning: you can improve performance by putting a github api token in $github_token_file\n";
    $token = '';
}

$labelMappings = [
        GithubChangelogGenerator::LABEL_TYPE_ADDED => ['feature', 'anotherFeatureLabel'],
    GithubChangelogGenerator::LABEL_TYPE_CHANGED => ['enhancement', 'anotherEnhancementLabel'],
    GithubChangelogGenerator::LABEL_TYPE_FIXED => ['bug', 'anotherBugLabel']
];

$repository = new ins0\GitHub\Repository('librenms/librenms', $token);
$changelog = new ins0\GitHub\ChangelogGenerator($repository);

// The ChangelogGenerator::generate() method does throw
// exceptions, so remember to wrap your code in try/catch blocks.
try {
    $handle = fopen('doc/General/Changelog.md', 'w');

    if (!$handle) {
        throw new RuntimeException('Cannot open file for writing');
    }

    // Write markdown output to file
    fwrite($handle, $changelog->generate());
    fclose($handle);
} catch (Exception $e) {
    // handle exceptions...
    echo $e->getMessage() . PHP_EOL;
}
