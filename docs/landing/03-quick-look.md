---
view: components.packages.quick-look
title: A quick look
subtitle: A user favorites a post, and the post page shows its count.
file: routes/web.php
language: php
code: |
  use App\Models\Post;

  Route::post('posts/{post}/favorite', function (Post $post) {
      $created = request()->user()->favorite($post);

      return response()->json([
          'created' => $created,              // false when it was already favorited
          'favorites' => $post->favorites()->count(),
      ]);
  })->middleware('auth');

  Route::get('posts', function () {
      return Post::withCount('favorites')->latest()->paginate();
  });
---
