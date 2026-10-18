<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Process::preventStrayProcesses(false);

    $this->sandbox = sys_get_temp_dir().'/devnull-knowledge-workflow-'.Str::random(10);
    File::makeDirectory("{$this->sandbox}/bin", recursive: true);

    File::put("{$this->sandbox}/bin/cloud", <<<'BASH'
        #!/usr/bin/env bash
        set -euo pipefail
        case "$1" in
            environment:get)
                printf '%s\n' '{"branch":"production","application":{"repositoryFullName":"nunomaduro/dev-null.sh"},"currentDeploymentId":"deployment-test"}'
                ;;
            deployment:get)
                printf '{"status":"deployment.succeeded","commitHash":"%s"}\n' "$GITHUB_SHA"
                ;;
            command:run)
                printf '%s\n' "$COMMAND_OUTPUT"
                exit "$CLI_EXIT_CODE"
                ;;
            *) exit 99 ;;
        esac
        BASH);

    File::put("{$this->sandbox}/bin/git", <<<'BASH'
        #!/usr/bin/env bash
        set -euo pipefail
        case "$1" in
            fetch | merge-base) exit 0 ;;
            show) printf '# Knowledge base\n\nTest section.\n' ;;
            *) exit 99 ;;
        esac
        BASH);

    File::chmod("{$this->sandbox}/bin/cloud", 0755);
    File::chmod("{$this->sandbox}/bin/git", 0755);
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

it('requires a successful final remote command record and CLI exit code', function (string $output, int $cliExitCode, int $expectedExitCode): void {
    $result = Process::path($this->sandbox)
        ->env([
            'PATH' => "{$this->sandbox}/bin:".getenv('PATH'),
            'LARAVEL_CLOUD_TOKEN' => 'test-token',
            'LARAVEL_CLOUD_ENVIRONMENT_ID' => 'environment-test',
            'GITHUB_SHA' => str_repeat('a', 40),
            'GITHUB_REPOSITORY' => 'nunomaduro/dev-null.sh',
            'COMMAND_OUTPUT' => $output,
            'CLI_EXIT_CODE' => (string) $cliExitCode,
        ])
        ->timeout(5)
        ->run(['bash', base_path('.github/scripts/embed-knowledge-base.sh')]);

    expect($result->exitCode())->toBe($expectedExitCode);
})->with([
    'monitored success' => ["{\"status\":\"command.running\"}\n{\"status\":\"command.success\",\"exitCode\":0}", 0, 0],
    'remote failure despite successful CLI exit' => ['{"status":"command.failure","exitCode":1}', 0, 1],
    'failed status with zero exit code' => ['{"status":"command.failure","exitCode":0}', 0, 1],
    'successful status with nonzero exit code' => ['{"status":"command.success","exitCode":1}', 0, 1],
    'missing exit code' => ['{"status":"command.success"}', 0, 1],
    'nonterminal final record' => ["{\"status\":\"command.success\",\"exitCode\":0}\n{\"status\":\"command.running\"}", 0, 1],
    'empty response' => ['', 0, 1],
    'invalid JSON' => ['not json', 0, 1],
    'CLI failure despite successful record' => ['{"status":"command.success","exitCode":0}', 2, 2],
]);
