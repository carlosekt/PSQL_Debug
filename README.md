# PSQL_Debug
Phalcon SQL Debug Bar for version 3.x.x<br/>
Support Raw SQL, PHQL, replace phalcon placeholders<br/>
It shows queries, queries execution time, highlights long and erroneous queries, plus small system information ...

In public/index.php
1. After <?php open tag add:
```php
     define('SYSTEM_START_TIME', microtime(true));
     include 'path_to_class' . 'PSQL_Debug.php';
```

2. Into your config add variable:
```php
    ...
    'sql_debug' => true //false
    ...
```

2. After echo $application->handle()->getContent(); add:
```php
    if ($di->get('config')->sql_debug) {
        \CarlosEkt\PSQL_Debug::getInstance()->end(microtime(true));
    }
```

3. In loadServices() function, where you set DB connection in DI add:
```php
    //Example register a 'db' service in the container
    $di->set('db', function () use ($config) {
        $connection = new \Phalcon\Db\Adapter\Pdo\Mysql([
            'host' => $config->database->host,
            'username' => $config->database->username,
            'password' => $config->database->password,
            'dbname' => $config->database->dbname,
            'charset' => $config->database->charset
        ]);

        if ($config->sql_debug) {
            $eventsManager = new \Phalcon\Events\Manager();

            \CarlosEkt\PSQL_Debug::getInstance()->init(SYSTEM_START_TIME);
            $eventsManager->attach('db', function ($event) {
                /** @var Phalcon\Events\Event $event */
                if ($event->getType() === 'beforeQuery') {
                    \CarlosEkt\PSQL_Debug::getInstance()->queryStart(microtime(true));
                }
                if ($event->getType() === 'afterQuery') {
                    $sql = \CarlosEkt\PSQL_Debug::getInstance()->getLastQuery(true);
                    \CarlosEkt\PSQL_Debug::getInstance()->queryEnd($sql, microtime(true));
                }
            });
            $connection->setEventsManager($eventsManager);
        }
        return $connection;
    }, true);
```

At the bottom of the page will appear:
![alt text](https://i.imgur.com/ljqo3hc.png)