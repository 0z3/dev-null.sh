<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('renders pending commits oldest first', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->create(['title' => 'Newer']);
    Commit::factory()->pending()->create(['title' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->create();
    Commit::factory()->rejected()->create();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/show')
            ->has('commits.data', 2)
            ->where('commits.data.0.title', 'Older')
            ->where('commits.data.1.title', 'Newer')
            ->has('commits.data.0.user'));
});

it('may not be viewed by non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('moderation.show'));

    $response->assertForbidden();
});

it('may approve a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('may reject a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('may approve the requested pending commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $pending = Commit::factory()->for($admin)->pending()->count(25)->create();
    $approved = Commit::factory()->for($admin)->create(['updated_at' => now()->subDay()]);
    $rejected = Commit::factory()->for($admin)->rejected()->create(['updated_at' => now()->subDay()]);

    $this->actingAs($admin)
        ->post(route('pending-commit-approval.store'), [
            'commit_ids' => [...$pending->modelKeys(), $approved->id, $rejected->id],
        ])
        ->assertRedirectToRoute('moderation.show');

    foreach ($pending as $commit) {
        expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
    }

    expect(Commit::query()->pending()->count())->toBe(0)
        ->and($approved->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($approved->updated_at->isSameSecond(now()->subDay()))->toBeTrue()
        ->and($rejected->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and($rejected->updated_at->isSameSecond(now()->subDay()))->toBeTrue();

    $this->get(route('moderation.show'))
        ->assertInertia(fn ($page) => $page->has('commits.data', 0));
});

it('may approve an empty pending queue', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $this->actingAs($admin)
        ->post(route('pending-commit-approval.store'), ['commit_ids' => []])
        ->assertRedirectToRoute('moderation.show');

    expect(Commit::query()->count())->toBe(0);
});

it('approves pending commits from the moderation page', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    Commit::factory()->for($admin)->pending()->count(2)->create();

    $this->actingAs($admin);

    $page = visit(route('moderation.show'))
        ->assertSee('Approve 2 pending commits');

    $newArrival = Commit::factory()->for($admin)->pending()->create(['title' => 'Arrived after opening moderation']);

    $page->click('Approve 2 pending commits')
        ->assertSee('Arrived after opening moderation')
        ->assertSee('Approve 1 pending commit')
        ->assertNoJavaScriptErrors();

    expect($newArrival->refresh()->status)->toBe(CommitStatus::Pending)
        ->and(Commit::query()->pending()->count())->toBe(1);

    $page->click('Approve 1 pending commit')
        ->assertSee('No commits are waiting for review.')
        ->assertNotPresent('[data-test="approve-all-pending-commits-button"]');

    expect(Commit::query()->pending()->count())->toBe(0);
});

it('leaves unseen and newly arrived commits pending', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    Commit::factory()->for($admin)->pending()->count(25)->create();

    $response = $this->actingAs($admin)->get(route('moderation.show'));
    $loadedIds = collect($response->inertiaProps('commits.data'))->pluck('id')->all();
    expect($loadedIds)->toHaveCount(20);

    $newArrival = Commit::factory()->for($admin)->pending()->create();
    $rejected = Commit::query()->findOrFail($loadedIds[0]);
    $rejected->update(['status' => CommitStatus::Rejected]);

    $this->post(route('pending-commit-approval.store'), ['commit_ids' => $loadedIds])
        ->assertRedirectToRoute('moderation.show');

    expect($newArrival->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($rejected->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and(Commit::query()->pending()->count())->toBe(6)
        ->and(Commit::query()->where('status', CommitStatus::Approved)->count())->toBe(19);

    $this->post(route('pending-commit-approval.store'), ['commit_ids' => $loadedIds])
        ->assertRedirectToRoute('moderation.show');

    expect(Commit::query()->pending()->count())->toBe(6);
});

it('requires a valid explicit list of commit ids', function (array $payload): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->for($admin)->pending()->create();

    $this->actingAs($admin)
        ->postJson(route('pending-commit-approval.store'), $payload)
        ->assertUnprocessable();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
})->with([
    'missing ids' => [[]],
    'null ids' => [['commit_ids' => null]],
    'non array ids' => [['commit_ids' => 'all']],
    'invalid id' => [['commit_ids' => ['invalid']]],
]);

it('may not approve all pending commits as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $this->actingAs($commit->user)
        ->post(route('pending-commit-approval.store'))
        ->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('requires authentication to approve all pending commits', function (): void {
    $commit = Commit::factory()->pending()->create();

    $this->post(route('pending-commit-approval.store'))
        ->assertRedirectToRoute('home');

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('may not moderate commits as a non admin', function (string $route): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route($route, $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
})->with(['commit-approval.store', 'commit-rejection.store']);

it('requires authentication', function (): void {
    $response = $this->get(route('moderation.show'));

    $response->assertRedirectToRoute('home');
});
