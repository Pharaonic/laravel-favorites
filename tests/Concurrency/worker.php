<?php

/*
 * One worker of ConcurrencyTest: boots the package, waits for the shared
 * start time, then favorites, unfavorites or toggles one pair and prints 1 or 0.
 *
 * Usage: php worker.php <favorite|unfavorite|toggle> <user id> <post id> <start time>
 */

use Pharaonic\Laravel\Favorites\Tests\Concurrency\WorkerApplication;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;

require __DIR__.'/../../vendor/autoload.php';

[, $action, $userId, $postId, $start] = $argv;

(new WorkerApplication('worker'))->createApplication();

$user = User::query()->findOrFail($userId);
$post = Post::query()->findOrFail($postId);

if ((float) $start > microtime(true)) {
    time_sleep_until((float) $start);
}

switch ($action) {
    case 'favorite':
        $result = $user->favorite($post);

        break;
    case 'unfavorite':
        $result = $user->unfavorite($post);

        break;
    default:
        $result = $user->toggleFavorite($post);
}

echo $result ? '1' : '0';
