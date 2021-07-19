<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware; //for changing method from post to patch
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

//init App with requires
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

//for flash messages
session_start();
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

//names for routing
$router = $app->getRouteCollector()->getRouteParser();

//db for data
$repo = new App\PostRepository();

//for changing method from post to patch
$app->add(MethodOverrideMiddleware::class);

$app->get("/", function ($request, $response) use ($router) {
    $url = $router->urlFor('posts');
    $response = $response->write("<a href=$url>List of Posts</a>");
    return $this->get('renderer')->render($response, "index.phtml");
})->setName("main");

$app->get("/posts", function ($request, $response) use ($repo) {
    $flash = $this->get('flash')->getMessages();
    $posts = $repo->read();
    $params = [
        'flash' => $flash,
        'posts' => $posts
    ];
    return $this->get('renderer')->render($response, "/posts/index.phtml", $params);
})->setName('posts');

$app->get("/posts/{id}/edit", function ($request, $response, array $args) use ($repo) {
    $id = $args['id'];
    $post = $repo->find($id);
    $params = [
        'errors' => [],
        'postData' => $post
    ];
    return $this->get('renderer')->render($response, "/posts/edit.phtml", $params);
})->setName('editPost');

$app->patch("/posts/{id}", function ($request, $response, array $args) use ($router, $repo) {
    $id = $args['id'];
    $dataFromDb = $repo->find($id);
    $dataFromForm = $request->getParsedBodyParam('post');
    $validator = new \App\Validator();
    $errors = $validator->validate($dataFromForm);

    if (count($errors) === 0) {
        $dataFromDb['name'] = $dataFromForm['name'];
        $dataFromDb['body'] = $dataFromForm['body'];
        $repo->save($dataFromDb);
        $this->get('flash')->addMessage('success', 'Post has been updated');
        return $response->withRedirect($router->urlFor('posts'));
    }

    $params = [
        'errors' => $errors,
        'postData' => $dataFromDb
    ];
    return $this->get('renderer')->render($response->withStatus(422), "/posts/edit.phtml", $params);
});
$app->run();
