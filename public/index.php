<?php

  if (session_status() == PHP_SESSION_NONE)
  {
      session_start();
  }

  require_once __DIR__ . '/../vendor/autoload.php';

  use Monolog\Logger;
  use Monolog\Handler\StreamHandler;
  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;
  use Ramsey\Uuid\Uuid;
  use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

  $config = [
      'settings' => [
        'displayErrorDetails' => true,
        'determineRouteBeforeAppMiddleware' => true,
        'debug' => true,
        'addContentLengthHeader' => true,
        'routerCacheFile' => __DIR__ . '/../cache/routes.cache'
      ]
  ];

  $app = new \Slim\App($config);

  $container = $app -> getContainer();

  $container['logger'] = function()
  {
    $logger = new \Monolog\Logger('phaziz');
    $file_handler = new \Monolog\Handler\StreamHandler(__DIR__ . '/../logs/' . date('Y-m-d') . '-log.logfile');
    $logger -> pushHandler($file_handler);
    return $logger;
  };

  $container['csrf'] = function ()
  {
      return new \Slim\Csrf\Guard;
  };

  $container['uuid'] = function()
  {
    try
    {
      $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'Slim3DevUserAuth' . date('YmdHis'));
      return $uuid5 -> toString();
    }
    catch (UnsatisfiedDependencyException $e)
    {
        return 'Caught exception: ' . $e -> getMessage();
    }
  };

  $container['flash'] = function ()
  {
      return new \Slim\Flash\Messages();
  };

  $container['twig'] = function()
  {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../views/');
    $twig = new Twig_Environment($loader, [
      'cache' => __DIR__ . '/../cache/',
      'debug' => false,
      'strict_variables' => true,
      'autoescape' => 'html',
      'optimizations' => -1,
      'charset' => 'utf-8'
    ]);

    return $twig;
  };

  $app->add($container->get('csrf'));

  $app -> get('/', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('Root Path');

      return $this -> twig -> render('index.html', [
        'PageTitle' => 'Homepage'
      ]);
    }
  );

  $app -> get('/login', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('Login Path');

      $tNameKey = $this -> csrf -> getTokenNameKey();
      $tValueKey = $this -> csrf -> getTokenValueKey();
      $tName = $request -> getAttribute($tNameKey);
      $tValue = $request -> getAttribute($tValueKey);
      $messages = $this -> flash -> getMessages('login');

      return $this -> twig -> render('login.html', [
        'PageTitle' => 'Login',
        'tNameKey' => $tNameKey,
        'tName' => $tName,
        'tValueKey' => $tValueKey,
        'tValue' => $tValue,
        'uuid' => $this -> uuid,
        'messages' => $messages
      ]);
    }
  );

  $app -> post('/verify', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('Verify Path');

      $Username = $_POST['username'];
      $Password = $_POST['password'];
      $UUID = $_POST['uuid'];

      if($Username == 'user' && $Password == 'password')
      {
        $_SESSION['uuid'] = $UUID;

        $this -> flash -> addMessage('login', 'Successfully logged in!');
        return $response -> withRedirect('./backend');
      }
      else
      {
        $this -> flash -> addMessage('login', 'Bad user-credentials! User unknown!');
        return $response -> withRedirect('./login');
      }
    }
  );

  $app -> get('/logout', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('Logout Path: ' . $_SESSION['uuid']);

      session_destroy();
      $_SESSION = [];

      return $response -> withRedirect('../');
    }
  );

  $app -> get('/backend', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('Backend Path');

      if(!isset($_SESSION['uuid']))
      {
        $this -> flash -> addMessage('login', 'Bad user-credentials! User unknown!');
        return $response -> withRedirect('./login');
        exit;
      }

      $messages = $this -> flash -> getMessages('login');

      return $this -> twig -> render('backend.html', [
        'PageTitle' => 'Backend',
        'messages' => $messages
      ]);
    }
  );

  $app -> get('/404', function (Request $request, Response $response, $args) use ($app)
    {
      $this -> logger -> addInfo('404 Path');

      return $this -> twig -> render('404.html', [
        'PageTitle' => 'Ups Uh Oh 404'
      ]);
    }
  );

  $app -> run();
