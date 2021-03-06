<?php

require __DIR__ . '/vendor/autoload.php';

// Include our simple REST Service
require 'PageService.php';

use sforsman\Rest\Server;
use sforsman\Rest\AbstractJsonService;
use League\Event\Emitter;
use League\Event\AbstractEvent;
use League\Event\CallbackListener;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup a logger
$log = new Logger('API');
$log->pushHandler(new StreamHandler('/tmp/api_log.txt', Logger::WARNING));

// We are interested in some events generated by the server
$emitter = new Emitter();

// This will be emitted right before control is dispatched to the actual service
$callback = function (AbstractEvent $event, $param = null) use ($log) {
  // In the real world, you would (for an example) validate OAuth2 headers here
  $log->addNotice(serialize($param));
};
$emitter->addListener('dispatch', CallbackListener::fromCallable($callback));

// This will be emitted when an exception is going to be processed and "converted" into JSON
$callback = function($event, $param) use ($log) {
  $log->addError($param['exception']->getMessage());
};
$emitter->addListener('exception', CallbackListener::fromCallable($callback));

// This will be emitted when an PHP error (warning, notice, fatal) has happened and the processing
$callback = function($event, $errorStr) use ($log) {
  $log->addWarning($errorStr);
};
$emitter->addListener('error', CallbackListener::fromCallable($callback));

// Create the actual REST server
$api = new Server($emitter);

// Use the built-in error handlers that prevent any default PHP behavior and ensure all errors are
// cleanly returned as JSON. For logging purposes you should use the event listeners
$api->registerErrorHandlers();

// Register a REST endpoint/service called page (by default it will be placed in the v1 namespace)
// e.g. https://api.myapp.com/v1/page
$api->register('page', PageService::class);

// Process the request
$response = $api->run();
$response->send();