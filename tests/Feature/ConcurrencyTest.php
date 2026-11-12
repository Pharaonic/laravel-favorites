<?php

use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Symfony\Component\Process\Process;

/*
 * Separate PHP processes, each with its own connection, act on one pair at
 * the same moment. On SQLite the database file serializes the writes; on
 * MySQL and PostgreSQL (FAVORITES_DB) they really run in parallel.
 */

/**
 * Start eight workers on the pair at the same moment and collect what each returned.
 *
 * @param  array<string, string>  $env
 * @return array{0: User, 1: Post, 2: array<int, int>}
 */
function favoriteRace(array $env, string $action, bool $favorited = false): array
{
    $user = User::create();
    $post = Post::create();

    if ($favorited) {
        $user->favorite($post);
    }

    // Leave the workers time to boot, then let them all start together.
    $start = (string) (microtime(true) + 3);
    $processes = [];

    for ($i = 0; $i < 8; $i++) {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/../Processes/worker.php', $action, (string) $user->getKey(), (string) $post->getKey(), $start],
            null,
            $env,
            null,
            60
        );
        $process->start();
        $processes[] = $process;
    }

    $results = [];

    foreach ($processes as $process) {
        $process->wait();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput())
            ->and($process->getOutput())->toBeIn(['0', '1']);

        $results[] = (int) $process->getOutput();
    }

    return [$user, $post, $results];
}

test('simultaneous favorites create one row and one true', function () {
    [$user, $post, $results] = favoriteRace($this->useSharedDatabase(), 'favorite');

    expect(array_sum($results))->toBe(1)
        ->and(Favorite::query()->count())->toBe(1)
        ->and($user->hasFavorited($post))->toBeTrue();
});

test('simultaneous unfavorites delete the row once', function () {
    [$user, $post, $results] = favoriteRace($this->useSharedDatabase(), 'unfavorite', true);

    expect(array_sum($results))->toBe(1)
        ->and(Favorite::query()->count())->toBe(0)
        ->and($user->hasFavorited($post))->toBeFalse();
});

test('simultaneous toggles never duplicate the favorite', function () {
    [$user, $post] = favoriteRace($this->useSharedDatabase(), 'toggle');

    expect(Favorite::query()->count())->toBeLessThanOrEqual(1)
        ->and($user->hasFavorited($post))->toBe(Favorite::query()->count() === 1);
});
