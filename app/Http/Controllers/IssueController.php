<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIssue;
use App\Http\Requests\CreateIssueRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

final readonly class IssueController
{
    public function store(CreateIssueRequest $request, #[CurrentUser] User $user, CreateIssue $action): JsonResponse
    {
        try {
            return response()->json($action->handle(
                $user,
                $request->string('command')->value(),
                $request->string('body')->value(),
            ), 201);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'GitHub did not respond. Check the upstream issues before trying again. Your draft is safe.',
            ], 502);
        } catch (RequestException) {
            return response()->json([
                'message' => 'GitHub did not create the issue. Your draft is safe.',
            ], 502);
        }
    }
}
