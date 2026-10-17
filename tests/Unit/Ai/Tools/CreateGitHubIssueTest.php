<?php

declare(strict_types=1);

use App\Ai\Tools\CreateGitHubIssue;
use App\Services\GitHub;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;

it('asks the model only for a required title, body, and feature or bug label', function (): void {
    $tool = new CreateGitHubIssue(resolve(GitHub::class), 'secret-token');

    $schema = new ObjectSchema($tool->schema(new JsonSchemaTypeFactory))->toSchema();

    expect($schema)->toEqual([
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'maxLength' => 256],
            'body' => ['type' => 'string'],
            'label' => ['type' => 'string', 'enum' => ['enhancement', 'bug']],
        ],
        'required' => ['title', 'body', 'label'],
        'additionalProperties' => false,
    ]);
});

it('describes creating a feature or bug issue in the dev-null.sh repository', function (): void {
    $tool = new CreateGitHubIssue(resolve(GitHub::class), 'secret-token');

    $description = $tool->description();

    expect($description)
        ->toContain('nunomaduro/dev-null.sh')
        ->toContain('feature')
        ->toContain('bug')
        ->not->toContain('secret-token');
});
